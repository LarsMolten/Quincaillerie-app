<?php

namespace App\Rapports;

use App\Enums\StatutRetour;
use App\Enums\StatutVente;
use App\Enums\TypeRetour;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rapport des ventes : synthèse, série par jour / semaine / mois, par vendeur et par produit.
 * Chiffre d'affaires net = ventes validées − retours clients validés (datés du retour), comme le tableau de bord.
 * Filtres : vendeur (ventes et retours de ses ventes), catégorie (section par produit).
 */
class RapportVentes
{
    /**
     * @param  array{vendeur?: ?int, categorie?: ?int}  $filtres
     * @return array<string, mixed>
     */
    public function donnees(Periode $periode, array $filtres = []): array
    {
        $vendeur = $filtres['vendeur'] ?? null;
        $categorie = $filtres['categorie'] ?? null;

        $ventes = $this->ventes($periode, $vendeur);
        $retours = $this->retours($periode, $vendeur);

        $brut = (float) (clone $ventes)->sum('ventes.total');
        $nombre = (int) (clone $ventes)->count();
        $montantRetours = (float) (clone $retours)->sum('lignes_retour.total');
        $remises = (float) (clone $ventes)->sum('ventes.remise')
            + (float) DB::table('lignes_vente')->joinSub((clone $ventes)->select('ventes.id'), 'v', 'v.id', '=', 'lignes_vente.vente_id')->sum('lignes_vente.remise');
        $cout = (float) DB::table('lignes_vente')->joinSub((clone $ventes)->select('ventes.id'), 'v', 'v.id', '=', 'lignes_vente.vente_id')
            ->sum(DB::raw('lignes_vente.quantite * lignes_vente.prix_achat_unitaire'))
            - (float) (clone $retours)->sum(DB::raw('lignes_retour.quantite * ('.$this->coutMoyenRetour().')'));
        $net = $brut - $montantRetours;

        return [
            'synthese' => [
                'brut' => round($brut, 2),
                'retours' => round($montantRetours, 2),
                'net' => round($net, 2),
                'nombre' => $nombre,
                'panier' => $nombre > 0 ? round($brut / $nombre, 2) : 0.0,
                'remises' => round($remises, 2),
                'cout' => round($cout, 2),
                'marge' => round($net - $cout, 2),
                'taux_marge' => $net > 0 ? round(($net - $cout) / $net * 100, 1) : null,
            ],
            'serie' => $this->serie($periode, $ventes, $retours),
            'parVendeur' => $this->parVendeur($ventes, $retours),
            'parProduit' => $this->parProduit($ventes, $retours, $categorie),
        ];
    }

    /** Ventes validées de la période (et du vendeur). */
    private function ventes(Periode $periode, ?int $vendeur): Builder
    {
        return DB::table('ventes')
            ->where('ventes.statut', StatutVente::Validee->value)
            ->where('ventes.date_vente', '>=', $periode->debut)
            ->where('ventes.date_vente', '<', $periode->fin->copy()->addDay())
            ->when($vendeur, fn (Builder $q, int $id) => $q->where('ventes.utilisateur_id', $id));
    }

    /** Lignes des retours clients validés de la période (des ventes du vendeur). */
    private function retours(Periode $periode, ?int $vendeur): Builder
    {
        return DB::table('lignes_retour')
            ->join('retours', 'retours.id', '=', 'lignes_retour.retour_id')
            ->join('ventes', 'ventes.id', '=', 'retours.vente_id')
            ->where('retours.type', TypeRetour::Client->value)
            ->where('retours.statut', StatutRetour::Valide->value)
            ->whereDate('retours.date_retour', '>=', $periode->debut->toDateString())
            ->whereDate('retours.date_retour', '<=', $periode->fin->toDateString())
            ->when($vendeur, fn (Builder $q, int $id) => $q->where('ventes.utilisateur_id', $id));
    }

    /** Prix d'achat moyen du produit retourné dans sa vente d'origine (sous-requête SQL). */
    private function coutMoyenRetour(): string
    {
        return 'SELECT SUM(lv.quantite * lv.prix_achat_unitaire * 1.0) / SUM(lv.quantite) FROM lignes_vente lv
                WHERE lv.vente_id = retours.vente_id AND lv.produit_id = lignes_retour.produit_id';
    }

    /** @return list<array{cle: string, libelle: string, brut: float, retours: float, net: float, nombre: int}> */
    private function serie(Periode $periode, Builder $ventes, Builder $retours): array
    {
        $parJourVentes = (clone $ventes)->groupByRaw('DATE(ventes.date_vente)')
            ->selectRaw('DATE(ventes.date_vente) as jour, SUM(ventes.total) as total, COUNT(*) as nombre')->get();
        $parJourRetours = (clone $retours)->groupByRaw('DATE(retours.date_retour)')
            ->selectRaw('DATE(retours.date_retour) as jour, SUM(lignes_retour.total) as total')->pluck('total', 'jour');

        // Groupes continus (jours, semaines ou mois sans vente à 0)
        $groupes = [];
        for ($date = $periode->debut->copy(); $date <= $periode->fin; $date->addDay()) {
            $cle = $periode->groupe($date);
            $groupes[$cle] ??= ['cle' => $cle, 'libelle' => $periode->libelleGroupe($cle), 'brut' => 0.0, 'retours' => 0.0, 'net' => 0.0, 'nombre' => 0];
        }
        foreach ($parJourVentes as $ligne) {
            $cle = $periode->groupe(Carbon::parse($ligne->jour));
            $groupes[$cle]['brut'] += (float) $ligne->total;
            $groupes[$cle]['nombre'] += (int) $ligne->nombre;
        }
        foreach ($parJourRetours as $jour => $total) {
            $groupes[$periode->groupe(Carbon::parse($jour))]['retours'] += (float) $total;
        }

        return array_values(array_map(fn (array $g) => [...$g, 'net' => round($g['brut'] - $g['retours'], 2)], $groupes));
    }

    private function parVendeur(Builder $ventes, Builder $retours): Collection
    {
        $retoursParVendeur = (clone $retours)->groupBy('ventes.utilisateur_id')
            ->selectRaw('ventes.utilisateur_id, SUM(lignes_retour.total) as total')->pluck('total', 'utilisateur_id');

        return (clone $ventes)
            ->join('utilisateurs', 'utilisateurs.id', '=', 'ventes.utilisateur_id')
            ->groupBy('ventes.utilisateur_id', 'utilisateurs.nom')
            ->selectRaw('ventes.utilisateur_id as id, utilisateurs.nom, COUNT(*) as nombre, SUM(ventes.total) as brut')
            ->get()
            ->map(function ($ligne) use ($retoursParVendeur) {
                $retour = (float) ($retoursParVendeur[$ligne->id] ?? 0);

                return (object) [
                    'id' => $ligne->id,
                    'nom' => $ligne->nom,
                    'nombre' => (int) $ligne->nombre,
                    'brut' => (float) $ligne->brut,
                    'retours' => $retour,
                    'net' => round((float) $ligne->brut - $retour, 2),
                    'panier' => $ligne->nombre > 0 ? round((float) $ligne->brut / $ligne->nombre, 2) : 0.0,
                ];
            })
            ->sortByDesc('net')
            ->values();
    }

    private function parProduit(Builder $ventes, Builder $retours, ?int $categorie): Collection
    {
        $vendus = DB::table('lignes_vente')
            ->joinSub((clone $ventes)->select('ventes.id'), 'v', 'v.id', '=', 'lignes_vente.vente_id')
            ->groupBy('lignes_vente.produit_id')
            ->selectRaw('lignes_vente.produit_id, SUM(lignes_vente.quantite) as quantite, SUM(lignes_vente.total) as chiffre, SUM(lignes_vente.quantite * lignes_vente.prix_achat_unitaire) as cout')
            ->get()->keyBy('produit_id');

        $rendus = (clone $retours)->groupBy('lignes_retour.produit_id')
            ->selectRaw('lignes_retour.produit_id, SUM(lignes_retour.quantite) as quantite, SUM(lignes_retour.total) as chiffre, SUM(lignes_retour.quantite * ('.$this->coutMoyenRetour().')) as cout')
            ->get()->keyBy('produit_id');

        $ids = $vendus->keys()->merge($rendus->keys())->unique();

        return DB::table('produits')
            ->leftJoin('categories', 'categories.id', '=', 'produits.categorie_id')
            ->leftJoin('unites', 'unites.id', '=', 'produits.unite_id')
            ->whereIn('produits.id', $ids)
            ->when($categorie, fn (Builder $q, int $id) => $q->where('produits.categorie_id', $id))
            ->get(['produits.id', 'produits.nom', 'produits.reference', 'categories.nom as categorie', 'unites.abreviation as unite'])
            ->map(function ($p) use ($vendus, $rendus) {
                $v = $vendus[$p->id] ?? null;
                $r = $rendus[$p->id] ?? null;
                $chiffre = (float) ($v->chiffre ?? 0) - (float) ($r->chiffre ?? 0);
                $cout = (float) ($v->cout ?? 0) - (float) ($r->cout ?? 0);

                return (object) [
                    ...(array) $p,
                    'quantite' => round((float) ($v->quantite ?? 0) - (float) ($r->quantite ?? 0), 3),
                    'retournes' => round((float) ($r->quantite ?? 0), 3),
                    'chiffre' => round($chiffre, 2),
                    'cout' => round($cout, 2),
                    'marge' => round($chiffre - $cout, 2),
                ];
            })
            ->sortByDesc('chiffre')
            ->values();
    }
}
