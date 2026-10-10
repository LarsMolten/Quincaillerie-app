<?php

namespace App\Rapports;

use App\Enums\StatutAchat;
use App\Enums\StatutRetour;
use App\Enums\TypeRetour;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rapport des achats : synthèse, série par période, par fournisseur et produits achetés.
 * Achats nets = achats validés − retours fournisseurs validés (datés du retour). Filtre : fournisseur.
 */
class RapportAchats
{
    /**
     * @param  array{fournisseur?: ?int}  $filtres
     * @return array<string, mixed>
     */
    public function donnees(Periode $periode, array $filtres = []): array
    {
        $fournisseur = $filtres['fournisseur'] ?? null;
        $achats = $this->achats($periode, $fournisseur);
        $retours = $this->retours($periode, $fournisseur);

        $total = (float) (clone $achats)->sum('achats.total');
        $montantRetours = (float) (clone $retours)->sum('lignes_retour.total');

        return [
            'synthese' => [
                'total' => round($total, 2),
                'nombre' => (int) (clone $achats)->count(),
                'paye' => round((float) (clone $achats)->sum('achats.montant_paye'), 2),
                'reste' => round((float) (clone $achats)->sum('achats.reste_a_payer'), 2),
                'retours' => round($montantRetours, 2),
                'net' => round($total - $montantRetours, 2),
            ],
            'serie' => $this->serie($periode, $achats, $retours),
            'parFournisseur' => $this->parFournisseur($achats, $retours),
            'produits' => $this->produits($achats, $retours),
        ];
    }

    private function achats(Periode $periode, ?int $fournisseur): Builder
    {
        return DB::table('achats')
            ->where('achats.statut', StatutAchat::Valide->value)
            ->whereDate('achats.date_achat', '>=', $periode->debut->toDateString())
            ->whereDate('achats.date_achat', '<=', $periode->fin->toDateString())
            ->when($fournisseur, fn (Builder $q, int $id) => $q->where('achats.fournisseur_id', $id));
    }

    private function retours(Periode $periode, ?int $fournisseur): Builder
    {
        return DB::table('lignes_retour')
            ->join('retours', 'retours.id', '=', 'lignes_retour.retour_id')
            ->join('achats', 'achats.id', '=', 'retours.achat_id')
            ->where('retours.type', TypeRetour::Fournisseur->value)
            ->where('retours.statut', StatutRetour::Valide->value)
            ->whereDate('retours.date_retour', '>=', $periode->debut->toDateString())
            ->whereDate('retours.date_retour', '<=', $periode->fin->toDateString())
            ->when($fournisseur, fn (Builder $q, int $id) => $q->where('achats.fournisseur_id', $id));
    }

    /** @return list<array{cle: string, libelle: string, total: float, retours: float, net: float, nombre: int}> */
    private function serie(Periode $periode, Builder $achats, Builder $retours): array
    {
        $groupes = [];
        for ($date = $periode->debut->copy(); $date <= $periode->fin; $date->addDay()) {
            $cle = $periode->groupe($date);
            $groupes[$cle] ??= ['cle' => $cle, 'libelle' => $periode->libelleGroupe($cle), 'total' => 0.0, 'retours' => 0.0, 'net' => 0.0, 'nombre' => 0];
        }
        foreach ((clone $achats)->groupByRaw('DATE(achats.date_achat)')->selectRaw('DATE(achats.date_achat) as jour, SUM(achats.total) as total, COUNT(*) as nombre')->get() as $ligne) {
            $cle = $periode->groupe(Carbon::parse($ligne->jour));
            $groupes[$cle]['total'] += (float) $ligne->total;
            $groupes[$cle]['nombre'] += (int) $ligne->nombre;
        }
        foreach ((clone $retours)->groupByRaw('DATE(retours.date_retour)')->selectRaw('DATE(retours.date_retour) as jour, SUM(lignes_retour.total) as total')->pluck('total', 'jour') as $jour => $montant) {
            $groupes[$periode->groupe(Carbon::parse($jour))]['retours'] += (float) $montant;
        }

        return array_values(array_map(fn (array $g) => [...$g, 'net' => round($g['total'] - $g['retours'], 2)], $groupes));
    }

    private function parFournisseur(Builder $achats, Builder $retours): Collection
    {
        $retoursParFournisseur = (clone $retours)->groupBy('achats.fournisseur_id')
            ->selectRaw('achats.fournisseur_id, SUM(lignes_retour.total) as total')->pluck('total', 'fournisseur_id');

        return (clone $achats)
            ->join('fournisseurs', 'fournisseurs.id', '=', 'achats.fournisseur_id')
            ->groupBy('achats.fournisseur_id', 'fournisseurs.nom')
            ->selectRaw('achats.fournisseur_id as id, fournisseurs.nom, COUNT(*) as nombre, SUM(achats.total) as total, SUM(achats.montant_paye) as paye, SUM(achats.reste_a_payer) as reste')
            ->get()
            ->map(fn ($l) => (object) [
                'id' => $l->id,
                'nom' => $l->nom,
                'nombre' => (int) $l->nombre,
                'total' => (float) $l->total,
                'paye' => (float) $l->paye,
                'reste' => (float) $l->reste,
                'retours' => (float) ($retoursParFournisseur[$l->id] ?? 0),
            ])
            ->sortByDesc('total')
            ->values();
    }

    private function produits(Builder $achats, Builder $retours): Collection
    {
        $rendus = (clone $retours)->groupBy('lignes_retour.produit_id')
            ->selectRaw('lignes_retour.produit_id, SUM(lignes_retour.quantite) as quantite, SUM(lignes_retour.total) as montant')
            ->get()->keyBy('produit_id');

        return DB::table('lignes_achat')
            ->joinSub((clone $achats)->select('achats.id'), 'a', 'a.id', '=', 'lignes_achat.achat_id')
            ->join('produits', 'produits.id', '=', 'lignes_achat.produit_id')
            ->leftJoin('unites', 'unites.id', '=', 'produits.unite_id')
            ->groupBy('lignes_achat.produit_id', 'produits.nom', 'produits.reference', 'unites.abreviation')
            ->selectRaw('lignes_achat.produit_id as id, produits.nom, produits.reference, unites.abreviation as unite, SUM(lignes_achat.quantite) as quantite, SUM(lignes_achat.total) as montant')
            ->get()
            ->map(function ($l) use ($rendus) {
                $quantite = (float) $l->quantite;

                return (object) [
                    ...(array) $l,
                    'quantite' => $quantite,
                    'montant' => (float) $l->montant,
                    'prix_moyen' => $quantite > 0 ? round((float) $l->montant / $quantite, 2) : 0.0,
                    'retournes' => (float) ($rendus[$l->id]->quantite ?? 0),
                    'montant_retours' => (float) ($rendus[$l->id]->montant ?? 0),
                ];
            })
            ->sortByDesc('montant')
            ->values();
    }
}
