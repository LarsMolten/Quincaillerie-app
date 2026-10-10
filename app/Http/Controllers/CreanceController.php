<?php

namespace App\Http\Controllers;

use App\Enums\ModePaiement;
use App\Enums\StatutVente;
use App\Models\Client;
use App\Models\Fournisseur;
use App\Models\Vente;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Créances (droit paiements.gerer) : clients qui nous doivent de l'argent, de la plus forte créance
 * à la plus faible, avec l'ancienneté de la plus vieille dette, le détail par facture et l'encaissement.
 * Le « Client comptoir » n'a jamais de crédit (CLAUDE.md §5.8).
 */
class CreanceController extends Controller
{
    public const TRANCHES = ['recente' => ['< 30 jours', 'info'], 'moyenne' => ['30–60 jours', 'alerte'], 'ancienne' => ['> 60 jours', 'danger']];

    public function index(Request $requete): View
    {
        $recherche = trim((string) $requete->query('recherche'));
        $anciennete = array_key_exists((string) $requete->query('anciennete'), self::TRANCHES) ? $requete->query('anciennete') : null;

        $impayees = fn (Builder $q) => $q->where('statut', StatutVente::Validee)->where('reste_a_payer', '>', 0);

        $debiteurs = Client::query()
            ->where('nom', '!=', Client::COMPTOIR)
            ->whereHas('ventes', $impayees)
            ->withSum(['ventes as du' => $impayees], 'reste_a_payer')
            ->withCount(['ventes as documents' => $impayees])
            ->addSelect(['plus_ancienne' => Vente::selectRaw('MIN(date_vente)')
                ->whereColumn('client_id', 'clients.id')
                ->where('statut', StatutVente::Validee)
                ->where('reste_a_payer', '>', 0)])
            ->when($recherche !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('nom', 'like', "%{$recherche}%")
                ->orWhere('telephone', 'like', "%{$recherche}%")))
            ->orderByDesc('du')
            ->orderBy('nom')
            ->get();

        return $this->liste($requete, $debiteurs, $recherche, $anciennete, 'creances');
    }

    /** Détail (fragment) : ventes impayées du client, de la plus ancienne à la plus récente. */
    public function show(Client $client): View
    {
        $ventes = $client->ventes()
            ->where('statut', StatutVente::Validee)
            ->where('reste_a_payer', '>', 0)
            ->with('facture')
            ->oldest('date_vente')
            ->oldest('id')
            ->get();

        return view('creances._detail', compact('client', 'ventes'));
    }

    /** Tranche d'ancienneté : moins de 30 jours, 30 à 60 jours, plus de 60 jours. */
    public static function tranche(int $jours): string
    {
        return match (true) {
            $jours < 30 => 'recente',
            $jours <= 60 => 'moyenne',
            default => 'ancienne',
        };
    }

    /** Jours écoulés depuis une date (début de journée). */
    public static function jours(mixed $date): int
    {
        return (int) Carbon::parse($date)->startOfDay()->diffInDays(today());
    }

    /**
     * Liste commune aux créances et aux dettes : ancienneté, filtre, synthèse et pagination manuelle
     * (quelques centaines de tiers au plus ; l'ancienneté est calculée sur la liste triée).
     *
     * @param  Collection<int, Client|Fournisseur>  $tiers  avec du, documents et plus_ancienne
     */
    public static function liste(Request $requete, Collection $tiers, string $recherche, ?string $anciennete, string $vue): View
    {
        $tiers = $tiers
            ->each(fn ($t) => $t->jours = self::jours($t->plus_ancienne))
            ->when($anciennete, fn (Collection $liste) => $liste->filter(fn ($t) => self::tranche($t->jours) === $anciennete))
            ->values();

        $page = max(1, $requete->integer('page', 1));
        $donnees = [
            'liste' => new LengthAwarePaginator(
                $tiers->forPage($page, 15)->values(), $tiers->count(), 15, $page,
                ['path' => $requete->url(), 'query' => $requete->query()],
            ),
            'recherche' => $recherche,
            'anciennete' => $anciennete,
            'synthese' => [
                'total' => (float) $tiers->sum('du'),
                'nombre' => $tiers->count(),
                'plusDe60' => (float) $tiers->filter(fn ($t) => $t->jours > 60)->sum('du'),
            ],
            'modes' => ModePaiement::encaissements(),
        ];

        return view($requete->header('X-Fragment') === 'liste' ? "{$vue}._liste" : "{$vue}.index", $donnees);
    }
}
