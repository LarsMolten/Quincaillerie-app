<?php

namespace App\Console\Commands;

use App\Models\Produit;
use App\Services\MouvementStockService;
use Illuminate\Console\Command;

/**
 * Contrôle de cohérence du stock : compare le stock enregistré au stock recalculé
 * à partir des mouvements. Ne corrige rien (ajustement ou inventaire à faire par un responsable).
 * Codes de sortie : 0 = cohérent, 1 = anomalies, 2 = produit introuvable.
 */
class VerifierStock extends Command
{
    protected $signature = 'stock:verifier {--produit= : Identifiant ou référence d\'un produit à contrôler}';

    protected $description = 'Vérifie que le stock de chaque produit correspond à ses mouvements de stock';

    public function handle(MouvementStockService $stock): int
    {
        $produit = null;

        if ($filtre = $this->option('produit')) {
            $produit = Produit::withTrashed()
                ->where(fn ($q) => $q->where('reference', $filtre)->when(ctype_digit((string) $filtre), fn ($q) => $q->orWhere('id', (int) $filtre)))
                ->first();

            if (! $produit) {
                $this->error("Produit introuvable : « {$filtre} ».");

                return 2;
            }
        }

        $controles = $produit ? 1 : Produit::withTrashed()->count();
        $this->info("Contrôle du stock de {$controles} produit(s)…");

        $anomalies = $stock->verifierCoherence($produit);

        if ($anomalies->isEmpty()) {
            $this->info("Stock cohérent : {$controles} produit(s) vérifié(s), aucun écart.");

            return self::SUCCESS;
        }

        $this->table(
            ['Référence', 'Produit', 'Stock enregistré', 'Stock recalculé', 'Écart', 'Mouvements incohérents'],
            $anomalies->map(fn (array $ligne) => [
                $ligne['reference'],
                $ligne['nom'],
                format_quantite($ligne['stock_actuel']),
                format_quantite($ligne['stock_calcule']),
                ($ligne['ecart'] > 0 ? '+' : '').format_quantite($ligne['ecart']),
                $ligne['ruptures_chaine'] ? '#'.implode(', #', $ligne['ruptures_chaine']) : '—',
            ])->all(),
        );

        $this->error("{$anomalies->count()} anomalie(s) détectée(s). Corrigez par un ajustement de stock ou un inventaire.");

        return self::FAILURE;
    }
}
