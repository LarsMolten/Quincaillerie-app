<?php

namespace App\Http\Controllers;

use App\Enums\ModePaiement;
use App\Http\Controllers\Concerns\EnregistrePaiement;
use App\Http\Requests\PaiementRequest;
use App\Models\Achat;
use App\Models\Paiement;
use App\Models\Vente;
use App\Services\PaiementService;
use App\Support\ReponsePdf;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Paiements (droit paiements.gerer) : historique filtrable avec totaux par mode, encaissement
 * d'une vente (créances) et reçu PDF. Les paiements fournisseurs exigent en plus achats.voir.
 */
class PaiementController extends Controller
{
    use EnregistrePaiement;

    public const PERIODES = ['aujourdhui' => 'Aujourd\'hui', '7j' => '7 jours', '30j' => '30 jours', 'mois' => 'Ce mois', 'tout' => 'Tout'];

    public function __construct(private readonly PaiementService $paiements) {}

    public function index(Request $requete): View
    {
        $voitAchats = (bool) $requete->user()->can('achats.voir');
        $modes = array_map(fn (ModePaiement $m) => $m->value, ModePaiement::encaissements());

        $filtres = [
            'periode' => array_key_exists((string) $requete->query('periode', 'mois'), self::PERIODES) ? (string) $requete->query('periode', 'mois') : 'mois',
            'mode' => in_array($requete->query('mode'), $modes, true) ? $requete->query('mode') : null,
            'sens' => $voitAchats && in_array($requete->query('sens'), ['encaissements', 'decaissements'], true) ? $requete->query('sens') : null,
            'recherche' => trim((string) $requete->query('recherche')),
        ];

        // Sans achats.voir : uniquement les encaissements clients
        $sens = $voitAchats ? $filtres['sens'] : 'encaissements';

        $base = Paiement::query()
            ->when($sens === 'encaissements', fn (Builder $q) => $q->where('payable_type', 'vente'))
            ->when($sens === 'decaissements', fn (Builder $q) => $q->where('payable_type', 'achat'))
            ->when($this->debutPeriode($filtres['periode']), fn (Builder $q, Carbon $debut) => $q->where('date_paiement', '>=', $debut))
            ->when($filtres['recherche'] !== '', function (Builder $q) use ($filtres) {
                $terme = '%'.$filtres['recherche'].'%';
                $q->where(fn (Builder $s) => $s->where('numero', 'like', $terme)
                    ->orWhere('reference', 'like', $terme)
                    ->orWhereHasMorph('payable', [Vente::class], fn (Builder $v) => $v->where('numero', 'like', $terme)
                        ->orWhereHas('client', fn (Builder $c) => $c->where('nom', 'like', $terme)))
                    ->orWhereHasMorph('payable', [Achat::class], fn (Builder $a) => $a->where('numero', 'like', $terme)
                        ->orWhereHas('fournisseur', fn (Builder $f) => $f->where('nom', 'like', $terme))));
            });

        $paiements = (clone $base)
            ->when($filtres['mode'], fn (Builder $q, string $mode) => $q->where('mode', $mode))
            ->with(['utilisateur', 'payable' => fn (MorphTo $m) => $m->morphWith([Vente::class => ['client', 'facture'], Achat::class => ['fournisseur']])])
            ->latest('date_paiement')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $donnees = compact('paiements', 'filtres', 'voitAchats');

        if ($requete->header('X-Fragment') === 'liste') {
            return view('paiements._liste', $donnees);
        }

        // Totaux par mode sur la période et la recherche (le filtre de mode ne s'y applique pas)
        $totaux = (clone $base)
            ->selectRaw('mode, payable_type, SUM(montant) as somme, COUNT(*) as nombre')
            ->groupBy('mode', 'payable_type')
            ->get();

        $parMode = collect(ModePaiement::encaissements())->map(fn (ModePaiement $mode) => [
            'mode' => $mode,
            'encaisse' => (float) $totaux->where('mode', $mode)->where('payable_type', 'vente')->sum('somme'),
            'decaisse' => (float) $totaux->where('mode', $mode)->where('payable_type', 'achat')->sum('somme'),
            'nombre' => (int) $totaux->where('mode', $mode)->sum('nombre'),
        ]);

        return view('paiements.index', [...$donnees, 'parMode' => $parMode]);
    }

    /** Encaissement ultérieur d'une vente (page Créances, fiche vente). */
    public function encaisser(PaiementRequest $requete, Vente $vente): JsonResponse|RedirectResponse
    {
        return $this->enregistrerPaiement($requete, $vente);
    }

    /** Reçu PDF : A5 (défaut) ou ticket 80 mm. */
    public function recu(Request $requete, Paiement $paiement): Response
    {
        abort_if($paiement->payable_type === 'achat' && ! $requete->user()->can('achats.voir'), 403, 'Vous n\'avez pas le droit d\'accéder à cette page.');

        $format = $requete->query('format') === PaiementService::FORMAT_TICKET ? PaiementService::FORMAT_TICKET : PaiementService::FORMAT_A5;

        return ReponsePdf::depuis($requete, $this->paiements->pdf($paiement, $format), $this->paiements->nomFichier($paiement));
    }

    private function debutPeriode(string $periode): ?Carbon
    {
        return match ($periode) {
            'aujourdhui' => today(),
            '7j' => today()->subDays(6),
            '30j' => today()->subDays(29),
            'mois' => today()->startOfMonth(),
            default => null,
        };
    }
}
