<?php

namespace App\Services;

use App\Enums\StatutFacture;
use App\Models\Facture;
use App\Models\Parametre;
use App\Models\Vente;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Factures des ventes : émission automatique (numéro FAC-AAAA-NNNNN sans trou, dans la transaction
 * de la vente) et annulation. Une facture émise n'est jamais modifiée (CLAUDE.md §5.6).
 */
class FactureService
{
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
}
