<?php

namespace App\Services;

use App\Enums\ModePaiement;
use App\Enums\StatutAchat;
use App\Enums\StatutRetour;
use App\Enums\StatutVente;
use App\Enums\TypeMouvementStock;
use App\Enums\TypeRetour;
use App\Exceptions\OperationRefuseeException;
use App\Models\Achat;
use App\Models\Produit;
use App\Models\Retour;
use App\Models\Vente;
use App\Support\CodeBarres;
use App\Support\Entreprise;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DocumentPdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Retours clients (sur une vente) et fournisseurs (sur un achat) : quantités limitées à ce qui reste
 * retournable, mouvements de stock (retour_client en entrée, retour_fournisseur en sortie), numéro
 * RET-AAAA-NNNNN sans trou, et règlement : le montant diminue d'abord le reste à payer du document
 * (avoir sur créance ou dette), l'excédent est remboursé. Annulation avec motif (CLAUDE.md §5.2, §5.6).
 */
class RetourService
{
    /** Motifs courants proposés par l'assistant (« Autre » exige une précision). */
    public const MOTIFS = [
        'client' => ['Produit défectueux', 'Erreur de produit', 'Erreur de quantité', 'Client a changé d\'avis', 'Autre'],
        'fournisseur' => ['Produit défectueux', 'Non conforme à la commande', 'Livraison en excès', 'Date dépassée / abîmé', 'Autre'],
    ];

    /** Rejeux en cas d'interblocage sur le premier numéro de l'année (voir NumerotationService). */
    private const TENTATIVES = 5;

    public function __construct(
        private readonly MouvementStockService $stock,
        private readonly NumerotationService $numerotation,
        private readonly JournalService $journal,
    ) {}

    /**
     * Retour client.
     *
     * @param  array<int|string, float>  $quantites  [produit_id => quantité retournée]
     */
    public function creerClient(Vente $vente, array $quantites, string $motif, ?ModePaiement $modeRemboursement = null): Retour
    {
        return $this->creer(TypeRetour::Client, $vente, $quantites, $motif, $modeRemboursement);
    }

    /**
     * Retour fournisseur.
     *
     * @param  array<int|string, float>  $quantites  [produit_id => quantité retournée]
     */
    public function creerFournisseur(Achat $achat, array $quantites, string $motif, ?ModePaiement $modeRemboursement = null): Retour
    {
        return $this->creer(TypeRetour::Fournisseur, $achat, $quantites, $motif, $modeRemboursement);
    }

    /**
     * Ce qui peut encore être retourné sur un document, par produit : quantité du document,
     * déjà retournée (retours validés), retournable et prix unitaire du retour.
     * Prix d'une vente : prix net des lignes, remise globale répartie au prorata ; d'un achat : prix d'achat.
     *
     * @return Collection<int, array{produit: Produit, quantite: float, retourne: float, retournable: float, prix_unitaire: float}>
     */
    public function retournables(Vente|Achat $document): Collection
    {
        $estVente = $document instanceof Vente;
        $lignes = $document->lignes()->with('produit.unite')->get();
        $ratio = $estVente && (float) $document->sous_total > 0 ? (float) $document->total / (float) $document->sous_total : 1.0;

        $retournes = $document->retours()->valides()
            ->join('lignes_retour', 'lignes_retour.retour_id', '=', 'retours.id')
            ->groupBy('lignes_retour.produit_id')
            ->selectRaw('lignes_retour.produit_id, SUM(lignes_retour.quantite) as quantite')
            ->pluck('quantite', 'produit_id');

        return $lignes->groupBy('produit_id')->map(function (Collection $groupe, int $produitId) use ($retournes, $ratio) {
            $quantite = round($groupe->sum(fn ($l) => (float) $l->quantite), 3);
            $montant = (float) $groupe->sum(fn ($l) => (float) $l->total);
            $retourne = round((float) ($retournes[$produitId] ?? 0), 3);

            return [
                'produit' => $groupe->first()->produit,
                'quantite' => $quantite,
                'retourne' => $retourne,
                'retournable' => max(0, round($quantite - $retourne, 3)),
                'prix_unitaire' => $quantite > 0 ? round($montant / $quantite * $ratio, 2) : 0.0,
            ];
        });
    }

    /** Annule un retour : stock remis comme avant, reste à payer du document rétabli. Le numéro est conservé. */
    public function annuler(Retour $retour, string $motif): Retour
    {
        $motif = trim($motif);

        if ($motif === '') {
            throw new OperationRefuseeException('Indiquez le motif de l\'annulation.');
        }

        return DB::transaction(function () use ($retour, $motif) {
            $verrouille = Retour::query()->whereKey($retour->getKey())->lockForUpdate()->firstOrFail();

            if ($verrouille->statut === StatutRetour::Annule) {
                throw new OperationRefuseeException("Le retour {$verrouille->numero} est déjà annulé.");
            }

            $estClient = $verrouille->type === TypeRetour::Client;
            // Retour client annulé : les produits ressortent du stock ; retour fournisseur annulé : ils y reviennent
            $type = $estClient ? TypeMouvementStock::AjustementNegatif : TypeMouvementStock::AjustementPositif;

            foreach ($verrouille->lignes()->with('produit')->get() as $ligne) {
                $this->stock->enregistrer($ligne->produit, $type, (float) $ligne->quantite, $verrouille,
                    "Annulation du retour {$verrouille->numero} : {$motif}");
            }

            if ((float) $verrouille->montant_avoir > 0) {
                /** @var Vente|Achat $document */
                $document = ($estClient ? Vente::query() : Achat::query())->whereKey($estClient ? $verrouille->vente_id : $verrouille->achat_id)->lockForUpdate()->firstOrFail();
                $document->update(['reste_a_payer' => round((float) $document->reste_a_payer + (float) $verrouille->montant_avoir, 2)]);
            }

            $verrouille->update(['statut' => StatutRetour::Annule]);

            $this->journal->enregistrer('retour.annule', $verrouille, [
                'numero' => $verrouille->numero,
                'motif' => $motif,
                'total' => (float) $verrouille->total,
                'avoir_retabli' => (float) $verrouille->montant_avoir,
                'rembourse' => (float) $verrouille->montant_rembourse,
            ]);

            $retour->setRawAttributes($verrouille->getAttributes(), true);

            return $retour;
        }, self::TENTATIVES);
    }

    /** Bon de retour A4. */
    public function pdf(Retour $retour): DocumentPdf
    {
        $retour->load(['lignes.produit.unite', 'utilisateur', 'vente.client', 'vente.facture', 'achat.fournisseur']);

        return Pdf::loadView('retours.bon', [
            'retour' => $retour,
            'document' => $retour->document(),
            'tiers' => $retour->type === TypeRetour::Client ? $retour->vente?->client?->nom : $retour->achat?->fournisseur?->nom,
            'codeBarres' => CodeBarres::pngBase64($retour->numero, 1, 36),
            'entreprise' => Entreprise::donnees(),
        ])->setPaper('a4');
    }

    public function nomFichier(Retour $retour): string
    {
        return 'retour-'.$retour->numero.'.pdf';
    }

    /** @param  array<int|string, float>  $quantites */
    private function creer(TypeRetour $type, Vente|Achat $document, array $quantites, string $motif, ?ModePaiement $modeRemboursement): Retour
    {
        $motif = trim($motif);

        if ($motif === '') {
            throw new OperationRefuseeException('Indiquez le motif du retour.');
        }

        if ($modeRemboursement === ModePaiement::Credit) {
            throw new OperationRefuseeException('Le crédit n\'est pas un mode de remboursement.');
        }

        $quantites = collect($quantites)
            ->map(fn ($q) => is_numeric($q) ? round((float) $q, 3) : 0.0)
            ->filter(fn (float $q) => $q > 0);

        if ($quantites->isEmpty()) {
            throw new OperationRefuseeException('Indiquez au moins un produit à retourner.');
        }

        $estClient = $type === TypeRetour::Client;

        return DB::transaction(function () use ($type, $estClient, $document, $quantites, $motif, $modeRemboursement) {
            // Verrou sur le document : deux retours simultanés ne dépassent jamais la quantité du document
            /** @var Vente|Achat $verrouille */
            $verrouille = $document::query()->whereKey($document->getKey())->lockForUpdate()->firstOrFail();

            if (in_array($verrouille->statut, [StatutVente::Annulee, StatutAchat::Annule], true)) {
                throw new OperationRefuseeException("Le document {$verrouille->numero} est annulé : aucun retour possible.");
            }

            $retournables = $this->retournables($verrouille);
            $lignes = [];

            foreach ($quantites as $produitId => $quantite) {
                $disponible = $retournables->get((int) $produitId);

                if ($disponible === null) {
                    throw new OperationRefuseeException("Ce produit ne figure pas dans le document {$verrouille->numero}.");
                }

                if ($quantite > $disponible['retournable'] + 0.0005) {
                    throw new OperationRefuseeException(sprintf(
                        'Retour impossible pour « %s » : %s retournable(s) sur %s, %s demandé(s).',
                        $disponible['produit']->nom,
                        format_quantite($disponible['retournable']),
                        format_quantite($disponible['quantite']),
                        format_quantite($quantite),
                    ));
                }

                $lignes[] = [
                    'produit' => $disponible['produit'],
                    'quantite' => $quantite,
                    'prix_unitaire' => $disponible['prix_unitaire'],
                    'total' => round($quantite * $disponible['prix_unitaire']),
                ];
            }

            $total = (float) array_sum(array_column($lignes, 'total'));
            $avoir = round(min($total, max(0, (float) $verrouille->reste_a_payer)), 2);
            $rembourse = round($total - $avoir, 2);

            if ($rembourse > 0 && $modeRemboursement === null) {
                throw new OperationRefuseeException(sprintf(
                    'Choisissez le mode de remboursement des %s %s.',
                    format_ar($rembourse),
                    $estClient ? 'à rendre au client' : 'à recevoir du fournisseur',
                ));
            }

            $retour = Retour::create([
                'numero' => $this->numerotation->suivant(Retour::class, $this->numerotation->prefixe('retour'), today()),
                'type' => $type,
                'vente_id' => $estClient ? $verrouille->id : null,
                'achat_id' => $estClient ? null : $verrouille->id,
                'utilisateur_id' => Auth::id(),
                'date_retour' => today(),
                'total' => $total,
                'montant_avoir' => $avoir,
                'montant_rembourse' => $rembourse,
                'mode_remboursement' => $rembourse > 0 ? $modeRemboursement : null,
                'motif' => mb_substr($motif, 0, 255),
                'statut' => StatutRetour::Valide,
            ]);

            foreach ($lignes as $ligne) {
                $retour->lignes()->create([
                    'produit_id' => $ligne['produit']->id,
                    'quantite' => $ligne['quantite'],
                    'prix_unitaire' => $ligne['prix_unitaire'],
                    'total' => $ligne['total'],
                ]);

                $this->stock->enregistrer(
                    $ligne['produit'],
                    $estClient ? TypeMouvementStock::RetourClient : TypeMouvementStock::RetourFournisseur,
                    $ligne['quantite'],
                    $retour,
                    "Retour {$retour->numero} ({$verrouille->numero}) : {$motif}",
                );
            }

            if ($avoir > 0) {
                $verrouille->update(['reste_a_payer' => round((float) $verrouille->reste_a_payer - $avoir, 2)]);
            }

            $this->journal->enregistrer('retour.cree', $retour, [
                'numero' => $retour->numero,
                'document' => $verrouille->numero,
                'total' => $total,
                'avoir' => $avoir,
                'rembourse' => $rembourse,
                'motif' => $motif,
            ]);

            return $retour;
        }, self::TENTATIVES);
    }
}
