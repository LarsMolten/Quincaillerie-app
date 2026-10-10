<?php

namespace App\Services;

use App\Enums\StatutFacture;
use App\Models\Facture;
use App\Models\Parametre;
use App\Models\Vente;
use App\Support\CodeBarres;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DocumentPdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;

/**
 * Factures des ventes : émission automatique (numéro FAC-AAAA-NNNNN sans trou, dans la transaction
 * de la vente), annulation et documents PDF (A4 et ticket 80 mm). Une facture émise n'est jamais
 * modifiée (CLAUDE.md §5.6).
 */
class FactureService
{
    public const FORMAT_A4 = 'a4';

    public const FORMAT_TICKET = 'ticket';

    /** Conversion pour le format du papier DomPDF (1 mm = 72 / 25,4 points). */
    private const POINTS_PAR_MM = 72 / 25.4;

    public function __construct(private readonly NumerotationService $numerotation) {}

    public function emettre(Vente $vente): Facture
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('La facture doit être émise dans la transaction de la vente.');
        }

        $prefixe = strtoupper(trim((string) Parametre::valeur('prefixe_facture', 'FAC'))) ?: 'FAC';

        return $vente->facture()->create([
            'numero' => $this->numerotation->suivant(Facture::class, $prefixe, $vente->date_vente),
            'date_emission' => $vente->date_vente,
            'total' => $vente->total,
            'statut' => StatutFacture::Emise,
        ]);
    }

    /** Annule la facture d'une vente annulée : elle garde son numéro. */
    public function annuler(Facture $facture): Facture
    {
        if ($facture->statut !== StatutFacture::Annulee) {
            $facture->update(['statut' => StatutFacture::Annulee]);
        }

        return $facture;
    }

    /** Document PDF de la facture, au format A4 ou ticket 80 mm. */
    public function pdf(Facture $facture, string $format = self::FORMAT_A4): DocumentPdf
    {
        $donnees = $this->donnees($facture);

        if ($format === self::FORMAT_TICKET) {
            $vente = $donnees['vente'];
            // Rouleau de 80 mm de large ; hauteur (en mm) adaptée au contenu
            $hauteurMm = 105 + 11 * $vente->lignes->count()
                + ($vente->remise > 0 ? 6 : 0)
                + ($vente->reste_a_payer > 0 ? 6 : 0)
                + ($donnees['tva'] ? 12 : 0);

            return Pdf::loadView('factures.ticket', $donnees)
                ->setPaper([0, 0, 80 * self::POINTS_PAR_MM, $hauteurMm * self::POINTS_PAR_MM]);
        }

        return Pdf::loadView('factures.a4', $donnees)->setPaper('a4');
    }

    /** Nom du fichier PDF téléchargé. */
    public function nomFichier(Facture $facture, string $format = self::FORMAT_A4): string
    {
        return ($format === self::FORMAT_TICKET ? 'ticket-' : 'facture-').$facture->numero.'.pdf';
    }

    /**
     * Données communes aux gabarits PDF : entreprise, facture, vente, TVA, code-barres.
     *
     * @return array<string, mixed>
     */
    public function donnees(Facture $facture): array
    {
        $vente = $facture->vente()->with(['client', 'utilisateur', 'lignes.produit.unite', 'paiements'])->firstOrFail();
        $taux = (float) str_replace(',', '.', (string) Parametre::valeur('taux_tva', '0'));
        $total = (float) $vente->total;

        // Prix TTC : la TVA est comprise dans le total de la vente (« dont TVA »)
        $tva = null;
        if ($taux > 0) {
            $ht = round($total / (1 + $taux / 100));
            $tva = ['taux' => $taux, 'ht' => $ht, 'montant' => $total - $ht];
        }

        return [
            'facture' => $facture,
            'vente' => $vente,
            'annulee' => $facture->statut === StatutFacture::Annulee,
            'tva' => $tva,
            'codeBarres' => CodeBarres::pngBase64($facture->numero, 1, 36),
            'entreprise' => [
                'nom' => Parametre::valeur('nom_entreprise', config('app.name')),
                'adresse' => Parametre::valeur('adresse'),
                'telephone' => Parametre::valeur('telephone'),
                'email' => Parametre::valeur('email'),
                'nif_stat' => Parametre::valeur('nif_stat'),
                'pied' => Parametre::valeur('pied_de_facture'),
                'logo' => $this->logo(),
            ],
        ];
    }

    /** Logo de l'entreprise (paramètre « logo » : chemin sur le disque privé) en data URI, ou null. */
    private function logo(): ?string
    {
        $chemin = trim((string) Parametre::valeur('logo', ''));

        if ($chemin === '' || ! Storage::disk('local')->exists($chemin)) {
            return null;
        }

        $type = Storage::disk('local')->mimeType($chemin);

        return in_array($type, ['image/png', 'image/jpeg'], true)
            ? 'data:'.$type.';base64,'.base64_encode(Storage::disk('local')->get($chemin))
            : null;
    }
}
