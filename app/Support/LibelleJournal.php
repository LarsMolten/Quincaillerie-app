<?php

namespace App\Support;

use App\Models\JournalActivite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Présentation du journal d'activité en français : phrase (« a modifié le produit »), type d'action
 * (icône, couleur), libellés des champs modifiés ; filtres par type et par module.
 * Les actions viennent du trait Journalisable (« produit.modifie ») ou des services (« vente.annule »).
 */
class LibelleJournal
{
    /** Objets journalisés : module (nom morph) => [désignation, libellé du filtre]. */
    public const MODULES = [
        'produit' => ['le produit', 'Produits'],
        'categorie' => ['la catégorie', 'Catégories'],
        'unite' => ['l\'unité', 'Unités'],
        'fournisseur' => ['le fournisseur', 'Fournisseurs'],
        'client' => ['le client', 'Clients'],
        'vente' => ['la vente', 'Ventes'],
        'achat' => ['l\'achat', 'Achats'],
        'facture' => ['la facture', 'Factures'],
        'paiement' => ['le paiement', 'Paiements'],
        'retour' => ['le retour', 'Retours'],
        'inventaire' => ['l\'inventaire', 'Inventaires'],
        'stock' => ['le stock de', 'Stock'],
        'depense' => ['la dépense', 'Dépenses'],
        'utilisateur' => ['le compte de', 'Utilisateurs'],
        'role' => ['le rôle', 'Rôles et droits'],
        'parametre' => ['le paramètre', 'Paramètres'],
    ];

    /** Types d'action (filtre) : code => [libellé, icône, couleur, motifs SQL LIKE sur « action »]. */
    public const TYPES = [
        'connexions' => ['Connexions', 'log-in', 'neutre', ['connexion', 'connexion.%', 'deconnexion']],
        'creations' => ['Créations', 'circle-plus', 'succes', ['%.cree', '%.creee', '%.ouvert']],
        'modifications' => ['Modifications', 'pencil', 'info', ['%.modifie', '%.modifiee', '%.desactive%', '%.reactive%', '%.restaure%', '%.droits_modifies', '%.mot_de_passe_reinitialise', '%.valide', '%.ajuste', '%.remise', '%.enregistre', '%.envoyee', '%.partagee']],
        'suppressions' => ['Suppressions', 'trash-2', 'danger', ['%.supprime', '%.supprimee']],
        'annulations' => ['Annulations', 'ban', 'alerte', ['%.annule', '%.annulee', 'stock.negatif']],
    ];

    /** Noms lisibles des paramètres (objet des entrées « parametre.modifie »). */
    public const PARAMETRES = [
        'nom_entreprise' => 'Nom de l\'entreprise', 'adresse' => 'Adresse', 'telephone' => 'Téléphone', 'email' => 'Email',
        'nif_stat' => 'NIF / STAT', 'logo' => 'Logo', 'taux_tva' => 'Taux de TVA', 'format_facture' => 'Format de facture',
        'pied_de_facture' => 'Pied de facture', 'prefixe_facture' => 'Préfixe des factures', 'prefixe_vente' => 'Préfixe des ventes',
        'prefixe_achat' => 'Préfixe des achats', 'prefixe_recu' => 'Préfixe des reçus', 'prefixe_retour' => 'Préfixe des retours',
        'prefixe_inventaire' => 'Préfixe des inventaires', 'remise_max_pourcentage' => 'Remise maximale générale',
        'stock_negatif_autorise' => 'Vente en stock négatif', 'theme_defaut' => 'Thème par défaut', 'couleur_accent' => 'Couleur d\'accent',
    ];

    /** Verbes des actions (dernier segment, sans accord). */
    private const VERBES = [
        'cree' => 'a créé',
        'modifie' => 'a modifié',
        'supprime' => 'a supprimé',
        'restaure' => 'a restauré',
        'desactive' => 'a désactivé',
        'reactive' => 'a réactivé',
        'annule' => 'a annulé',
        'remise' => 'a accordé une remise sur',
        'ajuste' => 'a ajusté',
        'valide' => 'a validé',
        'ouvert' => 'a ouvert',
        'enregistre' => 'a enregistré',
        'envoye' => 'a envoyé par email',
        'partage' => 'a partagé',
        'droits_modifies' => 'a modifié les droits du',
        'mot_de_passe_reinitialise' => 'a réinitialisé le mot de passe de',
        'negatif' => 'a vendu en stock négatif',
    ];

    /** Libellés des champs (anciennes et nouvelles valeurs). */
    private const CHAMPS = [
        'nom' => 'Nom', 'email' => 'Email', 'telephone' => 'Téléphone', 'adresse' => 'Adresse', 'actif' => 'Actif',
        'role_id' => 'Rôle', 'password' => 'Mot de passe', 'description' => 'Description', 'remise_max' => 'Remise maximale (%)',
        'reference' => 'Référence', 'code_barres' => 'Code-barres', 'categorie_id' => 'Catégorie', 'unite_id' => 'Unité',
        'prix_achat' => 'Prix d\'achat', 'prix_vente' => 'Prix de vente', 'stock_minimum' => 'Stock minimum', 'image' => 'Photo',
        'plafond_credit' => 'Plafond de crédit', 'libelle' => 'Libellé', 'montant' => 'Montant', 'categorie' => 'Catégorie',
        'mode_paiement' => 'Mode de paiement', 'date_depense' => 'Date', 'notes' => 'Notes', 'cle' => 'Paramètre', 'valeur' => 'Valeur',
        'numero' => 'Numéro', 'total' => 'Total', 'statut' => 'Statut', 'client_id' => 'Client', 'fournisseur_id' => 'Fournisseur',
        'utilisateur_id' => 'Utilisateur', 'abreviation' => 'Abréviation', 'contact' => 'Contact',
    ];

    /**
     * @return array{verbe: string, objet: ?string, type: string, icone: string, couleur: string}
     */
    public static function presenter(JournalActivite $entree): array
    {
        $type = self::type($entree->action);
        [, $icone, $couleur] = self::TYPES[$type] ?? ['', 'history', 'neutre'];
        $nom = $entree->details['objet'] ?? $entree->details['nom'] ?? $entree->details['numero'] ?? null;
        if (str_starts_with($entree->action, 'parametre.') && $nom !== null) {
            $nom = self::PARAMETRES[$nom] ?? $nom;
        }

        if (in_array($entree->action, ['connexion', 'deconnexion', 'connexion.refusee'], true)) {
            return [
                'verbe' => match ($entree->action) {
                    'connexion' => 's\'est connecté(e)',
                    'deconnexion' => 's\'est déconnecté(e)',
                    default => 'a tenté de se connecter (compte désactivé)',
                },
                'objet' => null, 'type' => $type, 'icone' => $entree->action === 'deconnexion' ? 'log-out' : $icone, 'couleur' => $couleur,
            ];
        }

        $module = Str::before($entree->action, '.');
        $suffixe = Str::after($entree->action, '.');
        // Accord féminin retiré (« supprimee » → « supprime », « envoyee » → « envoye »)
        $racine = str_ends_with($suffixe, 'ee') ? substr($suffixe, 0, -1) : $suffixe;
        $verbe = self::VERBES[$suffixe] ?? self::VERBES[$racine] ?? str_replace('_', ' ', $suffixe);
        $designation = self::MODULES[$module][0] ?? $module;
        if (str_ends_with($verbe, ' du') || str_ends_with($verbe, ' de') || str_ends_with($verbe, ' sur')) {
            // « a modifié les droits du rôle », « a réinitialisé le mot de passe de Rakoto »
            $designation = $module === 'utilisateur' ? '' : Str::after($designation, ' ');
        }

        return [
            'verbe' => $verbe,
            'objet' => trim($designation.($nom !== null ? ' « '.$nom.' »' : '')),
            'type' => $type,
            'icone' => $icone,
            'couleur' => $couleur,
        ];
    }

    public static function type(string $action): string
    {
        foreach (self::TYPES as $code => [, , , $motifs]) {
            foreach ($motifs as $motif) {
                if (Str::is(str_replace('%', '*', $motif), $action)) {
                    return $code;
                }
            }
        }

        return 'autres';
    }

    /** Restreint une requête au type d'action donné. */
    public static function filtrerType(Builder $requete, string $type): Builder
    {
        $motifs = self::TYPES[$type][3] ?? [];

        return $requete->where(function (Builder $q) use ($motifs) {
            foreach ($motifs as $motif) {
                str_contains($motif, '%') ? $q->orWhere('action', 'like', $motif) : $q->orWhere('action', $motif);
            }
        });
    }

    public static function champ(string $cle): string
    {
        return self::CHAMPS[$cle] ?? Str::ucfirst(str_replace('_', ' ', $cle));
    }

    /** Valeur lisible d'un champ (booléens, vides, tableaux). */
    public static function valeur(mixed $valeur, ?string $cle = null): string
    {
        if ($cle === 'actif' || $cle === 'stock_negatif_autorise') {
            $valeur = in_array($valeur, [true, 1, '1'], true);
        }

        return match (true) {
            $valeur === null, $valeur === '' => '—',
            $valeur === true => 'Oui',
            $valeur === false => 'Non',
            is_array($valeur) => implode(', ', array_map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE), $valeur)),
            default => (string) $valeur,
        };
    }
}
