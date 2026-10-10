<?php

namespace App\Services;

use App\Enums\ModePaiement;
use App\Enums\StatutVente;
use App\Enums\TypeMouvementStock;
use App\Exceptions\OperationRefuseeException;
use App\Models\Client;
use App\Models\Produit;
use App\Models\Vente;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Ventes (caisse) : enregistrement (numéro, lignes au prix serveur, sorties de stock, paiement, facture)
 * et annulation (mouvements inverses, facture annulée), toujours dans une transaction (CLAUDE.md §5.2).
 */
class VenteService
{
    /** Tentatives d'une transaction de création en cas d'interblocage MySQL (numérotation). */
    private const TENTATIVES = 5;

    public const TARIF_DETAIL = 'detail';

    public const TARIF_GROS = 'gros';

    public function __construct(
        private readonly NumerotationService $numerotation,
        private readonly MouvementStockService $stock,
        private readonly PaiementService $paiements,
        private readonly FactureService $factures,
        private readonly JournalService $journal,
    ) {}

    /**
     * @param  list<array{produit_id: int, quantite: float, tarif?: string, remise?: float}>  $lignes
     * @param  float  $remiseGlobale  remise sur le ticket, en Ar
     * @param  float  $montantRecu  somme remise par le client (ignorée pour une vente à crédit)
     */
    public function creer(
        Client $client,
        array $lignes,
        float $remiseGlobale,
        ModePaiement $mode,
        float $montantRecu = 0,
        ?string $notes = null,
    ): Vente {
        if (! $client->actif || $client->trashed()) {
            throw new OperationRefuseeException("Le client « {$client->nom} » est désactivé.");
        }

        $lignes = $this->preparerLignes($lignes);
        $sousTotal = round(array_sum(array_column($lignes, 'total')), 2);
        $remiseGlobale = round(max(0, $remiseGlobale), 2);

        if ($remiseGlobale > $sousTotal) {
            throw new OperationRefuseeException('La remise ne peut pas dépasser le sous-total.');
        }

        $this->controlerRemises($lignes, $remiseGlobale);

        $total = round($sousTotal - $remiseGlobale, 2);
        [$paye, $recu, $monnaie] = $this->reglement($mode, round(max(0, $montantRecu), 2), $total);
        $reste = round($total - $paye, 2);

        return DB::transaction(function () use ($client, $lignes, $sousTotal, $remiseGlobale, $total, $mode, $paye, $recu, $monnaie, $reste, $notes) {
            // Verrou du client : deux ventes à crédit simultanées ne dépassent jamais le plafond
            $client = Client::query()->whereKey($client->getKey())->lockForUpdate()->firstOrFail();
            $creance = $client->creance_totale; // lue avant la création de cette vente

            $date = now();

            $vente = Vente::create([
                'numero' => $this->numerotation->suivant(Vente::class, $this->numerotation->prefixe('vente'), $date),
                'client_id' => $client->id,
                'utilisateur_id' => Auth::id(),
                'date_vente' => $date,
                'sous_total' => $sousTotal,
                'remise' => $remiseGlobale,
                'total' => $total,
                'montant_paye' => 0,
                'reste_a_payer' => $total,
                'montant_recu' => $recu,
                'monnaie_rendue' => $monnaie,
                'mode_paiement' => $mode,
                'statut' => StatutVente::Validee,
                'notes' => $notes,
            ]);

            foreach ($lignes as $ligne) {
                $vente->lignes()->create([
                    'produit_id' => $ligne['produit']->id,
                    'quantite' => $ligne['quantite'],
                    'prix_unitaire' => $ligne['prix_unitaire'],
                    // Coût figé au moment de la vente : base du calcul du bénéfice (§5.7)
                    'prix_achat_unitaire' => $ligne['produit']->prix_achat,
                    'remise' => $ligne['remise'],
                    'total' => $ligne['total'],
                ]);

                // Lève StockInsuffisantException (message français) : toute la vente est annulée
                $this->stock->enregistrer($ligne['produit'], TypeMouvementStock::Vente, $ligne['quantite'], $vente);
            }

            // Après le stock (message le plus utile en premier), toujours sous le verrou du client
            if ($reste > 0) {
                $this->controlerCredit($client, $creance, $reste);
            }

            if ($paye > 0) {
                $this->paiements->enregistrer($vente, $paye, $mode, null, $date);
            }

            $this->factures->emettre($vente);

            $remises = round(array_sum(array_column($lignes, 'remise')) + $remiseGlobale, 2);
            if ($remises > 0) {
                $this->journal->enregistrer('vente.remise', $vente, [
                    'numero' => $vente->numero,
                    'remise_lignes' => round($remises - $remiseGlobale, 2),
                    'remise_globale' => $remiseGlobale,
                    'pourcentage' => round($remises / $this->montantBrut($lignes) * 100, 2),
                ]);
            }

            return $vente->refresh();
        }, self::TENTATIVES);
    }

    /**
     * Annule une vente : remet en stock les quantités vendues (mouvements inverses),
     * annule la facture et journalise. Les paiements déjà reçus restent dans l'historique.
     */
    public function annuler(Vente $vente, string $motif): Vente
    {
        $motif = trim($motif);

        if ($motif === '') {
            throw new OperationRefuseeException('Indiquez le motif de l\'annulation.');
        }

        return DB::transaction(function () use ($vente, $motif) {
            $verrouillee = Vente::query()->whereKey($vente->getKey())->lockForUpdate()->firstOrFail();

            if ($verrouillee->statut === StatutVente::Annulee) {
                throw new OperationRefuseeException("La vente {$verrouillee->numero} est déjà annulée.");
            }

            // Les quantités déjà retournées seraient remises deux fois en stock
            if ($retour = $verrouillee->retours()->valides()->first()) {
                throw new OperationRefuseeException("La vente {$verrouillee->numero} a un retour validé : annulez d'abord le retour {$retour->numero}.");
            }

            $quantites = $verrouillee->lignes()->get()
                ->groupBy('produit_id')
                ->map(fn ($lignes) => round($lignes->sum(fn ($l) => (float) $l->quantite), 3));

            $produits = Produit::withTrashed()->whereIn('id', $quantites->keys())->get()->keyBy('id');

            foreach ($quantites as $produitId => $quantite) {
                $this->stock->enregistrer(
                    $produits[$produitId],
                    TypeMouvementStock::AjustementPositif,
                    $quantite,
                    $verrouillee,
                    "Annulation de la vente {$verrouillee->numero} : {$motif}",
                );
            }

            $verrouillee->update(['statut' => StatutVente::Annulee]);

            if ($facture = $verrouillee->facture) {
                $this->factures->annuler($facture);
            }

            $this->journal->enregistrer('vente.annule', $verrouillee, [
                'numero' => $verrouillee->numero,
                'motif' => $motif,
                'total' => (float) $verrouillee->total,
                'deja_paye' => (float) $verrouillee->montant_paye,
            ]);

            $vente->setRawAttributes($verrouillee->getAttributes(), true);

            return $verrouillee;
        });
    }

    /**
     * Relit les produits et fixe les prix côté serveur. Les lignes d'un même produit au même tarif
     * sont regroupées (quantités et remises cumulées).
     *
     * @return list<array{produit: Produit, quantite: float, tarif: string, prix_unitaire: float, remise: float, total: float}>
     */
    private function preparerLignes(array $lignes): array
    {
        $fusion = [];

        foreach ($lignes as $ligne) {
            $id = (int) ($ligne['produit_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $tarif = ($ligne['tarif'] ?? self::TARIF_DETAIL) === self::TARIF_GROS ? self::TARIF_GROS : self::TARIF_DETAIL;
            $cle = $id.'|'.$tarif;
            $fusion[$cle] = [
                'produit_id' => $id,
                'tarif' => $tarif,
                'quantite' => round(($fusion[$cle]['quantite'] ?? 0) + (float) ($ligne['quantite'] ?? 0), 3),
                'remise' => round(($fusion[$cle]['remise'] ?? 0) + max(0, (float) ($ligne['remise'] ?? 0)), 2),
            ];
        }

        if ($fusion === []) {
            throw new OperationRefuseeException('Le ticket est vide : ajoutez au moins un produit.');
        }

        $produits = Produit::whereIn('id', array_column($fusion, 'produit_id'))->get()->keyBy('id');
        $preparees = [];

        foreach ($fusion as $ligne) {
            $produit = $produits->get($ligne['produit_id']);

            if (! $produit?->actif) {
                throw new OperationRefuseeException('Un produit du ticket est introuvable ou désactivé'.($produit ? " (« {$produit->nom} »)" : '').'.');
            }
            if ($ligne['quantite'] <= 0) {
                throw new OperationRefuseeException("La quantité de « {$produit->nom} » doit être supérieure à 0.");
            }
            if ($ligne['tarif'] === self::TARIF_GROS && $produit->prix_gros === null) {
                throw new OperationRefuseeException("« {$produit->nom} » n'a pas de prix de gros.");
            }

            // Le prix vient toujours de la fiche produit, jamais du navigateur
            $prix = (float) ($ligne['tarif'] === self::TARIF_GROS ? $produit->prix_gros : $produit->prix_vente);
            $brut = round($ligne['quantite'] * $prix, 2);

            if ($ligne['remise'] > $brut) {
                throw new OperationRefuseeException("La remise sur « {$produit->nom} » dépasse le montant de la ligne.");
            }

            $preparees[] = [
                'produit' => $produit,
                'quantite' => $ligne['quantite'],
                'tarif' => $ligne['tarif'],
                'prix_unitaire' => $prix,
                'remise' => $ligne['remise'],
                'total' => round($brut - $ligne['remise'], 2),
            ];
        }

        return $preparees;
    }

    /** Remises : droit ventes.remise obligatoire et plafond paramétré (en % du montant brut). */
    private function controlerRemises(array $lignes, float $remiseGlobale): void
    {
        $remises = round(array_sum(array_column($lignes, 'remise')) + $remiseGlobale, 2);

        if ($remises <= 0) {
            return;
        }

        if (! Auth::user()?->can('ventes.remise')) {
            throw new OperationRefuseeException('Vous n\'avez pas le droit d\'accorder une remise.');
        }

        // Plafond du rôle du vendeur (Paramètres > Ventes), sinon le plafond général
        $plafond = (float) Auth::user()?->role?->plafondRemise();
        $brut = $this->montantBrut($lignes);

        if ($brut > 0 && $remises > round($brut * $plafond / 100, 2) + 0.001) {
            throw new OperationRefuseeException(sprintf(
                'La remise (%s) dépasse le plafond autorisé de %s %% (%s maximum).',
                format_ar($remises), str_replace('.', ',', (string) round($plafond, 2)), format_ar($brut * $plafond / 100),
            ));
        }
    }

    /**
     * Détermine le montant encaissé, le montant reçu et la monnaie rendue.
     *
     * @return array{0: float, 1: ?float, 2: ?float} [payé, reçu, monnaie]
     */
    private function reglement(ModePaiement $mode, float $recu, float $total): array
    {
        if ($mode === ModePaiement::Credit) {
            return [0.0, null, null];
        }

        if ($recu <= 0) {
            throw new OperationRefuseeException('Indiquez le montant reçu, ou choisissez « Crédit ».');
        }

        if ($recu > $total && $mode !== ModePaiement::Especes) {
            throw new OperationRefuseeException(sprintf(
                'Le montant reçu par %s ne peut pas dépasser le total (%s).', mb_strtolower($mode->libelle()), format_ar($total),
            ));
        }

        // Seules les espèces donnent lieu à un rendu de monnaie
        return [min($recu, $total), $recu, round(max(0, $recu - $total), 2)];
    }

    /** Crédit : interdit au Client comptoir, limité au plafond du client (§5.8). */
    private function controlerCredit(Client $client, float $creance, float $reste): void
    {
        if ($client->estComptoir()) {
            throw new OperationRefuseeException('Le crédit est interdit pour le Client comptoir : encaissez la totalité ou choisissez un client.');
        }

        if ($client->plafond_credit === null) {
            throw new OperationRefuseeException("Aucun crédit n'est autorisé pour « {$client->nom} » (pas de plafond de crédit).");
        }

        $plafond = (float) $client->plafond_credit;

        if ($creance + $reste > $plafond + 0.001) {
            throw new OperationRefuseeException(sprintf(
                'Plafond de crédit dépassé pour « %s » : %s déjà dus + %s = %s, plafond %s.',
                $client->nom, format_ar($creance), format_ar($reste), format_ar($creance + $reste), format_ar($plafond),
            ));
        }
    }

    private function montantBrut(array $lignes): float
    {
        return round(array_sum(array_map(fn ($l) => $l['quantite'] * $l['prix_unitaire'], $lignes)), 2);
    }
}
