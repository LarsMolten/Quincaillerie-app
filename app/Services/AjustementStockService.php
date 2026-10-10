<?php

namespace App\Services;

use App\Enums\TypeMouvementStock;
use App\Exceptions\OperationRefuseeException;
use App\Models\MouvementStock;
use App\Models\Produit;
use Illuminate\Support\Facades\DB;

/**
 * Ajustements manuels du stock (pages Entrées et Sorties, droit stock.ajuster) :
 * ajustement positif, ajustement négatif ou perte, toujours avec un motif et journalisés (CLAUDE.md §5.10).
 */
class AjustementStockService
{
    public const TYPES = [TypeMouvementStock::AjustementPositif, TypeMouvementStock::AjustementNegatif, TypeMouvementStock::Perte];

    public function __construct(
        private readonly MouvementStockService $stock,
        private readonly JournalService $journal,
    ) {}

    public function enregistrer(Produit $produit, TypeMouvementStock $type, float $quantite, string $motif): MouvementStock
    {
        $motif = trim($motif);

        if (! in_array($type, self::TYPES, true)) {
            throw new OperationRefuseeException('Seuls les ajustements et les pertes se saisissent à la main.');
        }

        if ($motif === '') {
            throw new OperationRefuseeException('Indiquez le motif de l\'ajustement.');
        }

        return DB::transaction(function () use ($produit, $type, $quantite, $motif) {
            $mouvement = $this->stock->enregistrer($produit, $type, $quantite, null, $motif);

            $this->journal->enregistrer('stock.ajuste', $produit, [
                'type' => $type->value,
                'quantite' => (float) $mouvement->quantite,
                'stock_avant' => (float) $mouvement->stock_avant,
                'stock_apres' => (float) $mouvement->stock_apres,
                'motif' => $motif,
            ]);

            return $mouvement;
        });
    }
}
