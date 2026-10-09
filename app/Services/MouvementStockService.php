<?php

namespace App\Services;

use App\Enums\SensMouvement;
use App\Enums\TypeMouvementStock;
use App\Exceptions\StockInsuffisantException;
use App\Models\MouvementStock;
use App\Models\Parametre;
use App\Models\Produit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Seule porte d'entrée pour modifier produits.stock_actuel (CLAUDE.md §5.1).
 * Chaque modification crée un enregistrement dans mouvements_stock, dans une transaction,
 * après verrouillage de la ligne produit (deux ventes simultanées ne lisent jamais le même stock).
 */
class MouvementStockService
{
    /** Tolérance de comparaison des quantités (colonnes decimal(12,3)). */
    private const TOLERANCE = 0.0005;

    public function __construct(private readonly JournalService $journal) {}

    public function enregistrer(
        Produit $produit,
        TypeMouvementStock $type,
        float $quantite,
        ?Model $reference = null,
        ?string $motif = null,
    ): MouvementStock {
        // Les quantités sont stockées avec 3 décimales : on arrondit avant tout calcul
        $quantite = is_finite($quantite) ? round($quantite, 3) : 0.0;

        if ($quantite <= 0) {
            throw new InvalidArgumentException('La quantité d\'un mouvement de stock doit être positive.');
        }

        $utilisateurId = Auth::id()
            ?? throw new RuntimeException('Un mouvement de stock doit être enregistré par un utilisateur connecté.');

        return DB::transaction(function () use ($produit, $type, $quantite, $reference, $motif, $utilisateurId) {
            // Le stock est relu sur la ligne verrouillée, jamais sur l'instance reçue (qui peut être périmée)
            $verrouille = Produit::withTrashed()->whereKey($produit->getKey())->lockForUpdate()->firstOrFail();

            $stockAvant = (float) $verrouille->stock_actuel;
            $stockApres = $type->sens() === SensMouvement::Entree
                ? round($stockAvant + $quantite, 3)
                : round($stockAvant - $quantite, 3);

            if ($stockApres < 0) {
                // Stock négatif interdit, sauf paramètre explicite (§5.4)
                if (! Parametre::actif('stock_negatif_autorise')) {
                    throw new StockInsuffisantException($verrouille, $stockAvant, $quantite);
                }

                $this->journal->enregistrer('stock.negatif', $verrouille, [
                    'type' => $type->value,
                    'stock_avant' => $stockAvant,
                    'quantite' => $quantite,
                    'stock_apres' => $stockApres,
                ]);
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

    /**
     * Contrôle de cohérence (ne modifie rien) :
     * - le stock enregistré doit égaler la somme des mouvements (entrées − sorties) ;
     * - chaque mouvement doit partir du stock laissé par le précédent (le premier part de 0).
     *
     * @return Collection<int, array{produit_id: int, reference: string, nom: string, stock_actuel: float, stock_calcule: float, ecart: float, ruptures_chaine: list<int>}>
     */
    public function verifierCoherence(?Produit $produit = null): Collection
    {
        $calcules = MouvementStock::query()
            ->when($produit, fn ($q) => $q->where('produit_id', $produit->getKey()))
            ->groupBy('produit_id')
            ->selectRaw("produit_id, SUM(CASE WHEN sens = 'entree' THEN quantite ELSE -quantite END) AS total")
            ->pluck('total', 'produit_id');

        $ruptures = $this->rupturesDeChaine($produit);

        return Produit::withTrashed()
            ->when($produit, fn ($q) => $q->whereKey($produit->getKey()))
            ->orderBy('id')
            ->get(['id', 'reference', 'nom', 'stock_actuel'])
            ->map(function (Produit $p) use ($calcules, $ruptures) {
                $calcule = round((float) ($calcules[$p->id] ?? 0), 3);
                $actuel = (float) $p->stock_actuel;

                return [
                    'produit_id' => $p->id,
                    'reference' => $p->reference,
                    'nom' => $p->nom,
                    'stock_actuel' => $actuel,
                    'stock_calcule' => $calcule,
                    'ecart' => round($actuel - $calcule, 3),
                    'ruptures_chaine' => $ruptures[$p->id] ?? [],
                ];
            })
            ->filter(fn (array $ligne) => abs($ligne['ecart']) > self::TOLERANCE || $ligne['ruptures_chaine'] !== [])
            ->values();
    }

    /** @return array<int, list<int>> identifiants des mouvements dont le stock_avant ne suit pas le précédent */
    private function rupturesDeChaine(?Produit $produit): array
    {
        $ruptures = [];
        $precedent = [];

        // Parcours par lots dans l'ordre des identifiants (chronologique) ; le stock précédent est suivi par produit
        MouvementStock::query()
            ->when($produit, fn ($q) => $q->where('produit_id', $produit->getKey()))
            ->select(['id', 'produit_id', 'stock_avant', 'stock_apres'])
            ->lazyById(1000)
            ->each(function (MouvementStock $mouvement) use (&$ruptures, &$precedent) {
                $attendu = $precedent[$mouvement->produit_id] ?? 0.0;

                if (abs((float) $mouvement->stock_avant - $attendu) > self::TOLERANCE) {
                    $ruptures[$mouvement->produit_id][] = $mouvement->id;
                }

                $precedent[$mouvement->produit_id] = (float) $mouvement->stock_apres;
            });

        return $ruptures;
    }
}
