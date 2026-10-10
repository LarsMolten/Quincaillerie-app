<?php

namespace App\Exports\Rapports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Feuille d'un classeur de rapport : titre d'onglet, en-têtes en français (en gras), lignes de valeurs
 * (montants et quantités en nombres pour rester calculables dans le tableur).
 */
class Feuille implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  list<string>  $entetes
     * @param  list<list<mixed>>  $lignes
     */
    public function __construct(
        private readonly string $titre,
        private readonly array $entetes,
        private readonly array $lignes,
    ) {}

    public function array(): array
    {
        return $this->lignes;
    }

    public function headings(): array
    {
        return $this->entetes;
    }

    public function title(): string
    {
        // Excel : 31 caractères au plus, sans : \ / ? * [ ]
        return mb_substr(str_replace([':', '\\', '/', '?', '*', '[', ']'], '-', $this->titre), 0, 31);
    }

    public function styles(Worksheet $feuille): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
