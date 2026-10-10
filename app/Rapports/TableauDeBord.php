<?php

namespace App\Rapports;

use App\Enums\StatutVente;
use App\Models\Client;
use App\Models\Fournisseur;
use App\Models\Produit;
use App\Models\Vente;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Indicateurs du tableau de bord, calculés par agrégations SQL (fuseau Indian/Antananarivo).
 * Ventes nettes et bénéfice : formule commune aux rapports (App\Rapports\Indicateurs).
 * Les statistiques lourdes sont mises en cache 60 secondes.
 */
class TableauDeBord
{
    public const DUREE_CACHE = 60;

    public const PERIODES = [7, 30, 90];

    public function __construct(private readonly Indicateurs $indicateurs) {}

    /**
     * Chiffres du jour et de la veille (tendance), avec la série des 7 derniers jours.
     *
     * @return array{ventes: array, benefice: array, achats: array, nombre_ventes: int, depenses: float, serie: array}
     */
    public function chiffresDuJour(?Carbon $jour = null): array
    {
        $jour = ($jour ?? today())->copy()->startOfDay();

        return Cache::remember("tableau-bord:jour:{$jour->toDateString()}", self::DUREE_CACHE, function () use ($jour) {
            $debut = $jour->copy()->subDays(6);
            $jours = $this->parJour($debut, $jour);
            $aujourdhui = $jours[$jour->toDateString()];
            $hier = $this->parJour($jour->copy()->subDay(), $jour->copy()->subDay())[$jour->copy()->subDay()->toDateString()];

            return [
                'ventes' => $this->indicateur($aujourdhui['ventes'], $hier['ventes']),
                'benefice' => $this->indicateur($aujourdhui['benefice'], $hier['benefice']),
                'achats' => $this->indicateur($aujourdhui['achats'], $hier['achats']),
                'nombre_ventes' => $aujourdhui['nombre'],
                'depenses' => $aujourdhui['depenses'],
                'serie' => [
                    'ventes' => array_column($jours, 'ventes'),
                    'benefice' => array_column($jours, 'benefice'),
                    'achats' => array_column($jours, 'achats'),
                ],
            ];
        });
    }

    /**
     * Produits, clients et fournisseurs actifs, avec les nouveaux du mois.
     *
     * @return array<string, array{total: int, nouveaux: int}>
     */
    public function compteurs(): array
    {
        return Cache::remember('tableau-bord:compteurs:'.today()->toDateString(), self::DUREE_CACHE, function () {
            $debutMois = today()->startOfMonth();
            $compter = fn ($requete) => ['total' => (clone $requete)->count(), 'nouveaux' => (clone $requete)->where('created_at', '>=', $debutMois)->count()];

            return [
                'produits' => $compter(Produit::query()->where('actif', true)),
                'clients' => $compter(Client::query()->where('actif', true)->where('nom', '!=', Client::COMPTOIR)),
                'fournisseurs' => $compter(Fournisseur::query()->where('actif', true)),
            ];
        });
    }

    /**
     * Ventes nettes par jour (7 ou 30 jours) ou par semaine (90 jours), total et tendance vs période précédente.
     *
     * @return array{jours: int, libelles: list<string>, valeurs: list<float>, total: float, precedent: float, tendance: ?float}
     */
    public function serieVentes(int $jours = 30): array
    {
        $jours = in_array($jours, self::PERIODES, true) ? $jours : 30;

        return Cache::remember("tableau-bord:serie:{$jours}:".today()->toDateString(), self::DUREE_CACHE, function () use ($jours) {
            $fin = today();
            $debut = $fin->copy()->subDays($jours - 1);
            $parJour = collect($this->parJour($debut, $fin));
            $total = (float) $parJour->sum('ventes');
            $precedent = (float) collect($this->parJour($debut->copy()->subDays($jours), $debut->copy()->subDay()))->sum('ventes');

            if ($jours === 90) {
                // Regroupement par semaine (lundi) pour rester lisible
                $groupes = $parJour->groupBy(fn ($v, string $date) => Carbon::parse($date)->startOfWeek()->toDateString());
                $libelles = $groupes->keys()->map(fn (string $date) => 'Sem. du '.Carbon::parse($date)->translatedFormat('j M'))->all();
                $valeurs = $groupes->map(fn (Collection $g) => round((float) $g->sum('ventes'), 2))->values()->all();
            } else {
                $libelles = $parJour->keys()->map(fn (string $date) => Carbon::parse($date)->translatedFormat($jours === 7 ? 'D j' : 'j M'))->all();
                $valeurs = $parJour->pluck('ventes')->map(fn ($v) => round((float) $v, 2))->values()->all();
            }

            return [
                'jours' => $jours,
                'libelles' => array_values($libelles),
                'valeurs' => array_values($valeurs),
                'total' => $total,
                'precedent' => $precedent,
                'tendance' => $this->tendance($total, $precedent),
            ];
        });
    }

    /** Produits actifs au minimum ou en dessous, du plus vide au moins vide (taux de remplissage). */
    public function stockFaible(int $limite = 8): Collection
    {
        return Produit::query()
            ->with('unite')
            ->where('actif', true)
            ->where('stock_minimum', '>', 0)
            ->whereColumn('stock_actuel', '<=', 'stock_minimum')
            // × 1.0 : division décimale aussi sous SQLite (sinon 8 / 10 = 0)
            ->orderByRaw('(stock_actuel * 1.0) / stock_minimum')
            ->orderBy('nom')
            ->limit($limite)
            ->get()
            ->each(fn (Produit $p) => $p->remplissage = (int) max(0, min(100, round((float) $p->stock_actuel / (float) $p->stock_minimum * 100))));
    }

    /** Top des produits vendus ce mois-ci (ventes validées) : quantité, chiffre d'affaires et part pour les barres. */
    public function topProduits(int $limite = 5): Collection
    {
        $top = DB::table('lignes_vente')
            ->join('ventes', 'ventes.id', '=', 'lignes_vente.vente_id')
            ->join('produits', 'produits.id', '=', 'lignes_vente.produit_id')
            ->leftJoin('unites', 'unites.id', '=', 'produits.unite_id')
            ->where('ventes.statut', StatutVente::Validee->value)
            ->where('ventes.date_vente', '>=', today()->startOfMonth())
            ->where('ventes.date_vente', '<', today()->addDay())
            ->groupBy('lignes_vente.produit_id', 'produits.nom', 'produits.reference', 'unites.abreviation')
            ->selectRaw('lignes_vente.produit_id as id, produits.nom, produits.reference, unites.abreviation as unite, SUM(lignes_vente.quantite) as quantite, SUM(lignes_vente.total) as chiffre')
            ->orderByDesc('chiffre')
            ->limit($limite)
            ->get();

        $maximum = (float) ($top->max('chiffre') ?: 1);

        return $top->map(fn ($ligne) => (object) [
            ...(array) $ligne,
            'quantite' => (float) $ligne->quantite,
            'chiffre' => (float) $ligne->chiffre,
            'part' => (int) round((float) $ligne->chiffre / $maximum * 100),
        ]);
    }

    /**
     * Créances clients en cours (ventes validées non soldées, hors comptoir).
     *
     * @return array{total: float, debiteurs: int, plus_de_60: float, principaux: Collection}
     */
    public function creances(int $limite = 5): array
    {
        $impayees = Vente::query()
            ->join('clients', 'clients.id', '=', 'ventes.client_id')
            ->where('ventes.statut', StatutVente::Validee)
            ->where('ventes.reste_a_payer', '>', 0)
            ->where('clients.nom', '!=', Client::COMPTOIR);

        $principaux = (clone $impayees)
            ->groupBy('clients.id', 'clients.nom')
            ->selectRaw('clients.id, clients.nom, SUM(ventes.reste_a_payer) as du, COUNT(*) as factures')
            ->orderByDesc('du')
            ->limit($limite)
            ->get();

        return [
            'total' => (float) (clone $impayees)->sum('ventes.reste_a_payer'),
            'debiteurs' => (int) (clone $impayees)->distinct()->count('ventes.client_id'),
            'plus_de_60' => (float) (clone $impayees)->where('ventes.date_vente', '<', today()->subDays(60))->sum('ventes.reste_a_payer'),
            'principaux' => $principaux,
        ];
    }

    public function dernieresVentes(int $limite = 6): Collection
    {
        return Vente::query()->with(['client', 'facture'])->latest('date_vente')->latest('id')->limit($limite)->get();
    }

    /**
     * Agrégats par jour (voir Indicateurs : formule commune du bénéfice).
     *
     * @return array<string, array<string, float|int>>
     */
    public function parJour(Carbon $debut, Carbon $fin): array
    {
        return $this->indicateurs->parJour($debut, $fin);
    }

    /** Variation en % par rapport à la référence (null si la référence est nulle). */
    public function tendance(float $valeur, float $reference): ?float
    {
        return $reference == 0.0 ? null : round(($valeur - $reference) / abs($reference) * 100, 1);
    }

    /** @return array{valeur: float, precedent: float, tendance: ?float} */
    private function indicateur(float $valeur, float $precedent): array
    {
        return ['valeur' => $valeur, 'precedent' => $precedent, 'tendance' => $this->tendance($valeur, $precedent)];
    }
}
