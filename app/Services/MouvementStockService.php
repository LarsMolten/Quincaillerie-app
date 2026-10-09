<?php

namespace App\Services;

use App\Enums\SensMouvement;
use App\Enums\TypeMouvementStock;
use App\Exceptions\StockInsuffisantException;
use App\Models\MouvementStock;
use App\Models\Produit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Seule porte d'entrée pour modifier produits.stock_actuel.
 * Chaque modification crée un enregistrement dans mouvements_stock.
 */
class MouvementStockService
{
    public function enregistrer(
        Produit $produit,
        TypeMouvementStock $type,
        float $quantite,
        ?Model $reference = null,
        ?string $motif = null,
    ): MouvementStock {
        if ($quantite <= 0) {
            throw new InvalidArgumentException('La quantité d\'un mouvement de stock doit être positive.');
        }

        $utilisateurId = Auth::id()
            ?? throw new RuntimeException('Un mouvement de stock doit être enregistré par un utilisateur connecté.');

        return DB::transaction(function () use ($produit, $type, $quantite, $reference, $motif, $utilisateurId) {
            // Verrou sur la ligne produit : deux ventes simultanées ne lisent pas le même stock
            $verrouille = Produit::withTrashed()->whereKey($produit->getKey())->lockForUpdate()->firstOrFail();

            $stockAvant = (float) $verrouille->stock_actuel;
            $stockApres = $type->sens() === SensMouvement::Entree
                ? round($stockAvant + $quantite, 3)
                : round($stockAvant - $quantite, 3);

            if ($stockApres < 0) {
                throw new StockInsuffisantException($verrouille, $stockAvant, $quantite);
            }

            $verrouille->forceFill(['stock_actuel' => $stockApres])->save();

            // L'instance passée en paramètre reflète le nouveau stock
            $produit->forceFill(['stock_actuel' => $verrouille->stock_actuel])->syncOriginalAttribute('stock_actuel');

            return MouvementStock::create([
                'produit_id' => $verrouille->getKey(),
                'type' => $type,
                'sens' => $type->sens(),
                'quantite' => $quantite,
                'stock_avant' => $stockAvant,
                'stock_apres' => $stockApres,
                'utilisateur_id' => $utilisateurId,
                'motif' => $motif,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
            ]);
        });
    }
}
