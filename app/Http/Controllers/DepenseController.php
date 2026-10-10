<?php

namespace App\Http\Controllers;

use App\Enums\CategorieDepense;
use App\Exceptions\OperationRefuseeException;
use App\Http\Requests\DepenseRequest;
use App\Models\Depense;
use App\Services\DepenseService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Dépenses (droit depenses.gerer) : liste filtrée avec total et répartition par catégorie (anneau),
 * saisie et modification en modale ; suppression réservée à l'Administrateur.
 */
class DepenseController extends Controller
{
    public const PERIODES = [
        'aujourdhui' => 'Aujourd\'hui', '7j' => '7 jours', '30j' => '30 jours',
        'mois' => 'Ce mois', 'mois_dernier' => 'Mois dernier', 'tout' => 'Tout',
    ];

    public function __construct(private readonly DepenseService $depenses) {}

    public function index(Request $requete): View
    {
        $date = fn (string $cle) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $requete->query($cle)) ? (string) $requete->query($cle) : null;
        $periode = (string) $requete->query('periode', 'mois');

        $filtres = [
            'periode' => array_key_exists($periode, self::PERIODES) ? $periode : 'mois',
            'du' => $date('du'),
            'au' => $date('au'),
            'categorie' => CategorieDepense::tryFrom((string) $requete->query('categorie'))?->value,
            'recherche' => trim((string) $requete->query('recherche')),
        ];
        [$debut, $fin] = $this->bornes($filtres);

        $base = Depense::query()
            ->when($debut, fn (Builder $q, Carbon $d) => $q->whereDate('date_depense', '>=', $d->toDateString()))
            ->when($fin, fn (Builder $q, Carbon $f) => $q->whereDate('date_depense', '<=', $f->toDateString()))
            ->when($filtres['recherche'] !== '', fn (Builder $q) => $q->where(fn (Builder $s) => $s
                ->where('libelle', 'like', '%'.$filtres['recherche'].'%')
                ->orWhere('notes', 'like', '%'.$filtres['recherche'].'%')));

        // Répartition sur la période et la recherche (toutes catégories), pour l'anneau
        $parCategorie = (clone $base)->selectRaw('categorie, SUM(montant) as total, COUNT(*) as nombre')->groupBy('categorie')->get()->keyBy(fn ($l) => $l->categorie->value);
        $totalPeriode = (float) $parCategorie->sum('total');
        $repartition = collect(CategorieDepense::cases())
            ->map(fn (CategorieDepense $c) => [
                'categorie' => $c,
                'total' => (float) ($parCategorie[$c->value]->total ?? 0),
                'nombre' => (int) ($parCategorie[$c->value]->nombre ?? 0),
                'part' => $totalPeriode > 0 ? round((float) ($parCategorie[$c->value]->total ?? 0) / $totalPeriode * 100, 1) : 0,
            ])
            ->filter(fn (array $r) => $r['total'] > 0)
            ->sortByDesc('total')
            ->values();

        $selection = (clone $base)->when($filtres['categorie'], fn (Builder $q, string $c) => $q->where('categorie', $c));
        $nombre = (clone $selection)->count();
        $total = (float) (clone $selection)->sum('montant');

        $depenses = $selection->with('utilisateur')->latest('date_depense')->latest('id')->paginate(15)->withQueryString();

        $donnees = [
            'depenses' => $depenses,
            'filtres' => $filtres,
            'synthese' => ['total' => $total, 'nombre' => $nombre, 'moyenne' => $nombre > 0 ? $total / $nombre : 0, 'totalPeriode' => $totalPeriode],
            'repartition' => $repartition,
            'estAdministrateur' => $requete->user()->estAdministrateur(),
        ];

        return view($requete->header('X-Fragment') === 'liste' ? 'depenses._liste' : 'depenses.index', $donnees);
    }

    public function store(DepenseRequest $requete): JsonResponse
    {
        $depense = $this->depenses->creer($requete->validated());

        return response()->json(['message' => "Dépense « {$depense->libelle} » enregistrée : ".format_ar($depense->montant).'.'], 201);
    }

    public function update(DepenseRequest $requete, Depense $depense): JsonResponse
    {
        $this->depenses->modifier($depense, $requete->validated());

        return response()->json(['message' => "Dépense « {$depense->libelle} » modifiée."]);
    }

    public function destroy(Request $requete, Depense $depense): JsonResponse
    {
        try {
            $this->depenses->supprimer($depense, $requete->user());
        } catch (OperationRefuseeException $erreur) {
            return response()->json(['message' => $erreur->getMessage()], 403);
        }

        return response()->json(['message' => "Dépense « {$depense->libelle} » supprimée."]);
    }

    /** @return array{0: ?Carbon, 1: ?Carbon} */
    private function bornes(array $filtres): array
    {
        if ($filtres['du'] || $filtres['au']) {
            return [$filtres['du'] ? Carbon::parse($filtres['du']) : null, $filtres['au'] ? Carbon::parse($filtres['au']) : null];
        }

        return match ($filtres['periode']) {
            'aujourdhui' => [today(), null],
            '7j' => [today()->subDays(6), null],
            '30j' => [today()->subDays(29), null],
            'mois' => [today()->startOfMonth(), null],
            'mois_dernier' => [today()->subMonthNoOverflow()->startOfMonth(), today()->subMonthNoOverflow()->endOfMonth()],
            default => [null, null],
        };
    }
}
