<?php

namespace App\Services;

use App\Enums\ModePaiement;
use App\Enums\StatutAchat;
use App\Enums\TypeMouvementStock;
use App\Exceptions\OperationRefuseeException;
use App\Models\Achat;
use App\Models\Fournisseur;
use App\Models\Produit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Achats fournisseurs : enregistrement (numéro, lignes, entrées de stock, prix, paiement)
 * et annulation (mouvements inverses), toujours dans une transaction (CLAUDE.md §5.2).
 */
class AchatService
{
    /** Tentatives d'une transaction de création en cas d'interblocage MySQL. */
    private const TENTATIVES = 5;

    public function __construct(
        private readonly NumerotationService $numerotation,
        private readonly MouvementStockService $stock,
        private readonly PaiementService $paiements,
        private readonly JournalService $journal,
    ) {}

    /**
     * @param  list<array{produit_id: int, quantite: float, prix_achat: float}>  $lignes
     */
    public function creer(
        Fournisseur $fournisseur,
        array $lignes,
        float $montantPaye = 0,
        ?ModePaiement $mode = null,
        ?string $notes = null,
        ?Carbon $date = null,
        bool $majPrix = true,
    ): Achat {
        if (! $fournisseur->actif) {
            throw new OperationRefuseeException("Le fournisseur « {$fournisseur->nom} » est désactivé.");
        }

        $lignes = $this->fusionner($lignes);

        if ($lignes === []) {
            throw new OperationRefuseeException('Ajoutez au moins un produit à l\'achat.');
        }

        $produits = Produit::whereIn('id', array_column($lignes, 'produit_id'))->get()->keyBy('id');

        foreach ($lignes as $ligne) {
            $produit = $produits->get($ligne['produit_id']);
            if (! $produit?->actif) {
                throw new OperationRefuseeException('Un produit de l\'achat est introuvable ou désactivé'.($produit ? " (« {$produit->nom} »)" : '').'.');
            }
            if ($ligne['quantite'] <= 0 || $ligne['prix_achat'] < 0) {
                throw new OperationRefuseeException("Quantité ou prix invalide pour « {$produit->nom} ».");
            }
        }

        // Le total est toujours recalculé ici : jamais celui envoyé par le navigateur
        $total = round(array_sum(array_map(fn ($l) => $l['quantite'] * $l['prix_achat'], $lignes)), 2);
        $montantPaye = round($montantPaye, 2);

        if ($montantPaye < 0 || $montantPaye > $total) {
            throw new OperationRefuseeException(sprintf('Le montant payé doit être compris entre 0 et le total (%s).', format_ar($total)));
        }
        if ($montantPaye > 0 && ! $mode) {
            throw new OperationRefuseeException('Choisissez le mode de paiement du montant versé.');
        }

        $date ??= today();

        // Rejouée en cas d'interblocage (deux créations simultanées verrouillent le même intervalle de numéros) :
        // la tentative annulée ne laisse aucune trace, la suivante prend le numéro libre
        return DB::transaction(function () use ($fournisseur, $lignes, $produits, $total, $montantPaye, $mode, $notes, $date, $majPrix) {
            $achat = Achat::create([
                'numero' => $this->numerotation->suivant(Achat::class, 'ACH', $date),
                'fournisseur_id' => $fournisseur->id,
                'utilisateur_id' => Auth::id(),
                'date_achat' => $date,
                'total' => $total,
                'montant_paye' => 0,
                'reste_a_payer' => $total,
                'statut' => StatutAchat::Valide,
                'notes' => $notes,
            ]);

            foreach ($lignes as $ligne) {
                $produit = $produits[$ligne['produit_id']];

                $achat->lignes()->create([
                    'produit_id' => $produit->id,
                    'quantite' => $ligne['quantite'],
                    'prix_achat' => $ligne['prix_achat'],
                    'total' => round($ligne['quantite'] * $ligne['prix_achat'], 2),
                ]);

                $this->stock->enregistrer($produit, TypeMouvementStock::Achat, $ligne['quantite'], $achat);

                if ($majPrix && abs((float) $produit->prix_achat - $ligne['prix_achat']) > 0.001) {
                    $produit->update(['prix_achat' => $ligne['prix_achat']]);
                }
            }

            if ($montantPaye > 0) {
                $this->paiements->enregistrer($achat, $montantPaye, $mode, null, $date->copy()->setTimeFrom(now()));
            }

            return $achat->refresh();
        }, self::TENTATIVES);
    }

    /**
     * Annule un achat : retire du stock ce qui avait été reçu (mouvements inverses).
     * Refusé si le stock actuel ne couvre plus les quantités (une partie a déjà été vendue),
     * même si le stock négatif est autorisé par ailleurs.
     */
    public function annuler(Achat $achat, string $motif): Achat
    {
        $motif = trim($motif);

        if ($motif === '') {
            throw new OperationRefuseeException('Indiquez le motif de l\'annulation.');
        }

        return DB::transaction(function () use ($achat, $motif) {
            $verrouille = Achat::query()->whereKey($achat->getKey())->lockForUpdate()->firstOrFail();

            if ($verrouille->statut === StatutAchat::Annule) {
                throw new OperationRefuseeException("L'achat {$verrouille->numero} est déjà annulé.");
            }

            $quantites = $verrouille->lignes()->get()
                ->groupBy('produit_id')
                ->map(fn ($lignes) => round($lignes->sum(fn ($l) => (float) $l->quantite), 3));

            // Vérification préalable sur les lignes produit verrouillées
            $produits = Produit::withTrashed()->whereIn('id', $quantites->keys())->lockForUpdate()->get()->keyBy('id');
            foreach ($quantites as $produitId => $quantite) {
                $produit = $produits[$produitId];
                if ((float) $produit->stock_actuel + 0.0005 < $quantite) {
                    throw new OperationRefuseeException(sprintf(
                        'Annulation impossible : stock insuffisant pour « %s » (%s disponible(s), %s à retirer).',
                        $produit->nom, format_quantite($produit->stock_actuel), format_quantite($quantite),
                    ));
                }
            }

            foreach ($quantites as $produitId => $quantite) {
                $this->stock->enregistrer(
                    $produits[$produitId],
                    TypeMouvementStock::AjustementNegatif,
                    $quantite,
                    $verrouille,
                    "Annulation de l'achat {$verrouille->numero} : {$motif}",
                );
            }

            $verrouille->update(['statut' => StatutAchat::Annule]);

            $this->journal->enregistrer('achat.annule', $verrouille, [
                'numero' => $verrouille->numero,
                'motif' => $motif,
                'total' => (float) $verrouille->total,
                'deja_paye' => (float) $verrouille->montant_paye,
            ]);

            $achat->setRawAttributes($verrouille->getAttributes(), true);

            return $verrouille;
        });
    }

    /**
     * Regroupe les lignes d'un même produit au même prix (quantités cumulées).
     * Deux prix différents pour un produit restent deux lignes : le total saisi est respecté.
     *
     * @return list<array{produit_id: int, quantite: float, prix_achat: float}>
     */
    private function fusionner(array $lignes): array
    {
        $fusion = [];

        foreach ($lignes as $ligne) {
            $id = (int) ($ligne['produit_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $prix = round((float) ($ligne['prix_achat'] ?? 0), 2);
            $cle = $id.'|'.$prix;
            $fusion[$cle] = [
                'produit_id' => $id,
                'quantite' => round(($fusion[$cle]['quantite'] ?? 0) + (float) ($ligne['quantite'] ?? 0), 3),
                'prix_achat' => $prix,
            ];
        }

        return array_values($fusion);
    }
}
