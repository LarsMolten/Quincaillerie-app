<?php

namespace App\Services;

use App\Enums\ModePaiement;
use App\Enums\StatutAchat;
use App\Enums\StatutVente;
use App\Exceptions\OperationRefuseeException;
use App\Models\Achat;
use App\Models\Paiement;
use App\Models\Vente;
use App\Support\CodeBarres;
use App\Support\Entreprise;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DocumentPdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Enregistre les règlements d'un document (achat : décaissement, vente : encaissement),
 * tient à jour son montant payé et son reste à payer, et produit le reçu PDF.
 * Chaque paiement reçoit un numéro de reçu REC-AAAA-NNNNN sans trou (CLAUDE.md §5.6).
 */
class PaiementService
{
    public const FORMAT_A5 = 'a5';

    public const FORMAT_TICKET = 'ticket';

    /** Rejeux en cas d'interblocage sur le premier numéro de l'année (voir NumerotationService). */
    private const TENTATIVES = 5;

    /** Conversion pour le format du papier DomPDF (1 mm = 72 / 25,4 points). */
    private const POINTS_PAR_MM = 72 / 25.4;

    public function __construct(
        private readonly NumerotationService $numerotation,
        private readonly JournalService $journal,
    ) {}

    /**
     * Paiement enregistré après coup (créances, dettes, fiches), journalisé dans la même transaction.
     * Les règlements de la caisse et de la création d'un achat passent par enregistrer() seul.
     */
    public function enregistrerUlterieur(
        Achat|Vente $document,
        float $montant,
        ModePaiement $mode,
        ?string $reference = null,
        ?Carbon $date = null,
    ): Paiement {
        return DB::transaction(function () use ($document, $montant, $mode, $reference, $date) {
            $paiement = $this->enregistrer($document, $montant, $mode, $reference, $date);

            $this->journal->enregistrer('paiement.enregistre', $paiement, [
                'numero' => $paiement->numero,
                'document' => $document->numero,
                'montant' => (float) $paiement->montant,
                'mode' => $paiement->mode->value,
            ]);

            return $paiement;
        }, self::TENTATIVES);
    }

    public function enregistrer(
        Achat|Vente $document,
        float $montant,
        ModePaiement $mode,
        ?string $reference = null,
        ?Carbon $date = null,
    ): Paiement {
        $montant = round($montant, 2);

        if ($montant <= 0) {
            throw new OperationRefuseeException('Le montant du paiement doit être supérieur à 0.');
        }

        if ($mode === ModePaiement::Credit) {
            throw new OperationRefuseeException('Le crédit n\'est pas un mode de paiement : laissez un reste à payer.');
        }

        $date ??= now();

        // Imbriquée dans la transaction d'une vente ou d'un achat, c'est la transaction parente qui est rejouée
        return DB::transaction(function () use ($document, $montant, $mode, $reference, $date) {
            // Verrou sur le document : deux paiements simultanés ne dépassent jamais le reste
            /** @var Achat|Vente $verrouille */
            $verrouille = $document::query()->whereKey($document->getKey())->lockForUpdate()->firstOrFail();

            if (in_array($verrouille->statut, [StatutAchat::Annule, StatutVente::Annulee], true)) {
                throw new OperationRefuseeException("Le document {$verrouille->numero} est annulé : aucun paiement possible.");
            }

            $reste = (float) $verrouille->reste_a_payer;

            if ($montant > $reste + 0.001) {
                throw new OperationRefuseeException(sprintf(
                    'Le paiement (%s) dépasse le reste à payer de %s (%s).',
                    format_ar($montant), $verrouille->numero, format_ar($reste),
                ));
            }

            $paiement = $verrouille->paiements()->create([
                'numero' => $this->numerotation->suivant(Paiement::class, $this->numerotation->prefixe('paiement'), $date),
                'montant' => $montant,
                'mode' => $mode,
                'reference' => $reference,
                'date_paiement' => $date,
                'utilisateur_id' => Auth::id(),
            ]);

            $verrouille->update([
                'montant_paye' => round((float) $verrouille->montant_paye + $montant, 2),
                'reste_a_payer' => round($reste - $montant, 2),
            ]);

            // L'instance reçue reflète les nouveaux montants
            $document->setRawAttributes($verrouille->getAttributes(), true);

            return $paiement;
        }, self::TENTATIVES);
    }

    /** Reçu de paiement au format A5 ou ticket 80 mm. */
    public function pdf(Paiement $paiement, string $format = self::FORMAT_A5): DocumentPdf
    {
        $donnees = $this->donnees($paiement);

        if ($format === self::FORMAT_TICKET) {
            // Rouleau de 80 mm de large ; hauteur (en mm) adaptée au contenu
            $hauteurMm = 116 + ($paiement->reference ? 5 : 0) + ($donnees['annule'] ? 9 : 0);

            return Pdf::loadView('paiements.recu-ticket', $donnees)
                ->setPaper([0, 0, 80 * self::POINTS_PAR_MM, $hauteurMm * self::POINTS_PAR_MM]);
        }

        return Pdf::loadView('paiements.recu', $donnees)->setPaper('a5');
    }

    public function nomFichier(Paiement $paiement): string
    {
        return 'recu-'.$paiement->numero.'.pdf';
    }

    /**
     * Données du reçu : document réglé, tiers, situation avant et après ce paiement.
     *
     * @return array<string, mixed>
     */
    public function donnees(Paiement $paiement): array
    {
        $paiement->loadMissing('utilisateur');
        $document = $paiement->payable;
        $estVente = $document instanceof Vente;
        $document->load($estVente ? ['client', 'facture'] : ['fournisseur']);

        // Reste juste après ce paiement = reste actuel + règlements enregistrés depuis sur le même document
        $total = (float) $document->total;
        $resteApres = (float) $document->reste_a_payer + (float) $document->paiements()->where('id', '>', $paiement->id)->sum('montant');
        $avant = max(0, round($total - $resteApres - (float) $paiement->montant, 2));

        return [
            'paiement' => $paiement,
            'document' => $document,
            'estVente' => $estVente,
            'tiers' => $estVente ? $document->client?->nom : $document->fournisseur?->nom,
            'reference' => $estVente ? ($document->facture?->numero ?? $document->numero) : $document->numero,
            'annule' => in_array($document->statut, [StatutAchat::Annule, StatutVente::Annulee], true),
            'total' => $total,
            'avant' => $avant,
            'resteApres' => max(0, round($resteApres, 2)),
            'codeBarres' => CodeBarres::pngBase64($paiement->numero, 1, 36),
            'entreprise' => Entreprise::donnees(),
        ];
    }
}
