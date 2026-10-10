<?php

namespace App\Rapports;

use App\Enums\StatutVente;
use App\Enums\TypeMouvementStock;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rapport de stock : état actuel valorisé au prix d'achat (par catégorie), stock faible, ruptures,
 * produits sans vente depuis N jours (instantané à aujourd'hui) et mouvements de la période.
 * Filtre : catégorie.
 */
class RapportStock
{
    public const JOURS_SANS_VENTE = [30, 60, 90];

    public const MOUVEMENTS_ECRAN = 200;

    /**
     * @param  array{categorie?: ?int, jours?: ?int}  $filtres
     * @return array<string, mixed>
     */
    public function donnees(Periode $periode, array $filtres = []): array
    {
        $categorie = $filtres['categorie'] ?? null;
        $jours = in_array($filtres['jours'] ?? null, self::JOURS_SANS_VENTE, true) ? $filtres['jours'] : 30;

        $produits = $this->produits($categorie);
        $valorisable = fn ($p) => max(0, (float) $p->stock_actuel) * (float) $p->prix_achat;

        $parCategorie = $produits->groupBy('categorie')
            ->map(fn (Collection $groupe, string $nom) => (object) [
                'categorie' => $nom,
                'produits' => $groupe->count(),
                'valeur' => round($groupe->sum($valorisable), 2),
            ])
            ->sortByDesc('valeur')
            ->values();

        $mouvements = $this->mouvements($periode, $categorie);
        $liste = (clone $mouvements)
            ->leftJoin('unites', 'unites.id', '=', 'produits.unite_id')
            ->join('utilisateurs', 'utilisateurs.id', '=', 'mouvements_stock.utilisateur_id')
            ->select(['mouvements_stock.*', 'produits.nom as produit', 'produits.reference', 'unites.abreviation as unite', 'utilisateurs.nom as utilisateur'])
            ->orderByDesc('mouvements_stock.created_at')->orderByDesc('mouvements_stock.id');

        return [
            'synthese' => [
                'produits' => $produits->count(),
                'valeur' => round($produits->sum($valorisable), 2),
                'faibles' => $produits->where('etat', 'faible')->count(),
                'ruptures' => $produits->where('etat', 'rupture')->count(),
            ],
            'etat' => $produits->map(fn ($p) => (object) [...(array) $p, 'valeur' => round($valorisable($p), 2)]),
            'parCategorie' => $parCategorie,
            'faibles' => $produits->where('etat', 'faible')->sortBy(fn ($p) => (float) $p->stock_actuel / max(0.001, (float) $p->stock_minimum))->values(),
            'ruptures' => $produits->where('etat', 'rupture')->values(),
            'joursSansVente' => $jours,
            'sansVente' => $this->sansVente($produits, $jours),
            'mouvementsParType' => (clone $mouvements)
                ->groupBy('mouvements_stock.type', 'mouvements_stock.sens')
                ->selectRaw('mouvements_stock.type, mouvements_stock.sens, COUNT(*) as nombre, SUM(mouvements_stock.quantite) as quantite')
                ->get()
                ->map(fn ($l) => (object) ['type' => TypeMouvementStock::from($l->type), 'sens' => $l->sens, 'nombre' => (int) $l->nombre, 'quantite' => (float) $l->quantite])
                ->sortBy(fn ($l) => $l->type->libelle())
                ->values(),
            'mouvements' => $liste,
            'nombreMouvements' => (clone $mouvements)->count(),
        ];
    }

    /** Produits actifs avec catégorie, unité et état (normal, faible, rupture). */
    private function produits(?int $categorie): Collection
    {
        return DB::table('produits')
            ->leftJoin('categories', 'categories.id', '=', 'produits.categorie_id')
            ->leftJoin('unites', 'unites.id', '=', 'produits.unite_id')
            ->whereNull('produits.deleted_at')
            ->where('produits.actif', true)
            ->when($categorie, fn (Builder $q, int $id) => $q->where('produits.categorie_id', $id))
            ->orderBy('categories.nom')->orderBy('produits.nom')
            ->get(['produits.id', 'produits.nom', 'produits.reference', 'produits.stock_actuel', 'produits.stock_minimum', 'produits.prix_achat', 'produits.prix_vente',
                'categories.nom as categorie', 'unites.abreviation as unite'])
            ->map(function ($p) {
                $p->categorie ??= 'Sans catégorie';
                $p->stock_actuel = (float) $p->stock_actuel;
                $p->stock_minimum = (float) $p->stock_minimum;
                $p->prix_achat = (float) $p->prix_achat;
                $p->etat = match (true) {
                    $p->stock_actuel <= 0 => 'rupture',
                    $p->stock_actuel <= $p->stock_minimum => 'faible',
                    default => 'normal',
                };

                return $p;
            });
    }

    /** Produits sans vente validée depuis N jours (ou jamais vendus), avec la valeur immobilisée. */
    private function sansVente(Collection $produits, int $jours): Collection
    {
        $dernieres = DB::table('lignes_vente')
            ->join('ventes', 'ventes.id', '=', 'lignes_vente.vente_id')
            ->where('ventes.statut', StatutVente::Validee->value)
            ->whereIn('lignes_vente.produit_id', $produits->pluck('id'))
            ->groupBy('lignes_vente.produit_id')
            ->selectRaw('lignes_vente.produit_id, MAX(ventes.date_vente) as derniere')
            ->pluck('derniere', 'produit_id');

        $limite = today()->subDays($jours);

        return $produits
            ->map(fn ($p) => (object) [...(array) $p, 'derniere_vente' => $dernieres[$p->id] ?? null, 'valeur' => round(max(0, $p->stock_actuel) * $p->prix_achat, 2)])
            ->filter(fn ($p) => $p->derniere_vente === null || $p->derniere_vente < $limite->toDateTimeString())
            ->sortBy(fn ($p) => $p->derniere_vente ?? '0000')
            ->values();
    }

    /** Mouvements de la période (filtre seul : la liste et les regroupements y ajoutent leurs colonnes). */
    private function mouvements(Periode $periode, ?int $categorie): Builder
    {
        return DB::table('mouvements_stock')
            ->join('produits', 'produits.id', '=', 'mouvements_stock.produit_id')
            ->where('mouvements_stock.created_at', '>=', $periode->debut)
            ->where('mouvements_stock.created_at', '<', $periode->fin->copy()->addDay())
            ->when($categorie, fn (Builder $q, int $id) => $q->where('produits.categorie_id', $id));
    }
}
