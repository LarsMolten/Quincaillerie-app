<?php

namespace App\Exports;

use App\Models\MouvementStock;
use App\Rapports\FiltresMouvements;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export Excel des mouvements de stock, avec les mêmes filtres que la page.
 */
class MouvementsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    public function __construct(private readonly FiltresMouvements $filtres) {}

    public function query(): Builder
    {
        return $this->filtres->requete();
    }

    public function headings(): array
    {
        return ['Date', 'Produit', 'Référence produit', 'Type', 'Sens', 'Quantité', 'Unité', 'Stock avant', 'Stock après', 'Document', 'Utilisateur', 'Motif'];
    }

    /** @param  MouvementStock  $mouvement */
    public function map($mouvement): array
    {
        $entree = $mouvement->sens->value === 'entree';

        return [
            $mouvement->created_at->format('d/m/Y H:i'),
            $mouvement->produit?->nom,
            $mouvement->produit?->reference,
            $mouvement->type->libelle(),
            $entree ? 'Entrée' : 'Sortie',
            ($entree ? 1 : -1) * (float) $mouvement->quantite,
            $mouvement->produit?->unite?->abreviation,
            (float) $mouvement->stock_avant,
            (float) $mouvement->stock_apres,
            $mouvement->lienDocument(null)['libelle'] ?? '',
            $mouvement->utilisateur?->nom,
            $mouvement->motif,
        ];
    }

    public function styles(Worksheet $feuille): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
