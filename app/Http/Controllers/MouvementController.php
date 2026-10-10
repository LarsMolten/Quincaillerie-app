<?php

namespace App\Http\Controllers;

use App\Enums\SensMouvement;
use App\Enums\TypeMouvementStock;
use App\Exceptions\OperationRefuseeException;
use App\Exceptions\StockInsuffisantException;
use App\Exports\MouvementsExport;
use App\Http\Requests\AjustementStockRequest;
use App\Models\Produit;
use App\Models\Utilisateur;
use App\Rapports\FiltresMouvements;
use App\Services\AjustementStockService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Mouvements de stock (droit stock.voir) : historique filtrable, vues Entrées et Sorties, export Excel.
 * Ajustements manuels (perte, ajustement +/−) réservés au droit stock.ajuster.
 */
class MouvementController extends Controller
{
    public function __construct(private readonly AjustementStockService $ajustements) {}

    public function index(Request $requete): View
    {
        return $this->liste($requete, null);
    }

    public function entrees(Request $requete): View
    {
        return $this->liste($requete, SensMouvement::Entree);
    }

    public function sorties(Request $requete): View
    {
        return $this->liste($requete, SensMouvement::Sortie);
    }

    /** Export Excel avec les filtres affichés (le sens imposé des pages Entrées/Sorties est transmis en paramètre). */
    public function export(Request $requete): BinaryFileResponse
    {
        return Excel::download(new MouvementsExport(FiltresMouvements::depuis($requete)), 'mouvements-'.today()->format('Y-m-d').'.xlsx');
    }

    /** Produits actifs pour la modale d'ajustement (recherche par nom, référence ou code-barres). */
    public function produits(Request $requete): JsonResponse
    {
        $produits = Produit::query()->actif()->with('unite')
            ->recherche((string) $requete->query('q'))
            ->orderBy('nom')
            ->limit(12)
            ->get()
            ->map(fn (Produit $p) => [
                'id' => $p->id,
                'nom' => $p->nom,
                'reference' => $p->reference,
                'unite' => $p->unite?->abreviation,
                'stock' => (float) $p->stock_actuel,
            ]);

        return response()->json($produits);
    }

    public function ajuster(AjustementStockRequest $requete): JsonResponse
    {
        $produit = Produit::findOrFail($requete->validated('produit_id'));
        $type = TypeMouvementStock::from($requete->validated('type'));

        try {
            $mouvement = $this->ajustements->enregistrer($produit, $type, (float) $requete->validated('quantite'), $requete->validated('motif'));
        } catch (OperationRefuseeException|StockInsuffisantException $erreur) {
            return response()->json(['message' => $erreur->getMessage(), 'errors' => ['quantite' => [$erreur->getMessage()]]], 422);
        }

        return response()->json([
            'message' => sprintf('« %s » : stock %s → %s (%s).',
                $produit->nom, format_quantite($mouvement->stock_avant), format_quantite($mouvement->stock_apres), mb_strtolower($type->libelle())),
        ], 201);
    }

    private function liste(Request $requete, ?SensMouvement $sens): View
    {
        $filtres = FiltresMouvements::depuis($requete, $sens);
        $mouvements = $filtres->requete()->paginate(20)->withQueryString();

        $donnees = [
            'mouvements' => $mouvements,
            'filtres' => $filtres->valeurs,
            'filtresActifs' => $filtres->actifs($sens),
            'sens' => $sens,
        ];

        if ($requete->header('X-Fragment') === 'liste') {
            return view('stock._liste', $donnees);
        }

        return view('stock.mouvements', [
            ...$donnees,
            'utilisateurs' => Utilisateur::withTrashed()->whereHas('mouvementsStock')->orderBy('nom')->get(['id', 'nom']),
            'produit' => $filtres->valeurs['produit'] ? Produit::withTrashed()->find($filtres->valeurs['produit']) : null,
            'types' => collect(TypeMouvementStock::cases())->filter(fn (TypeMouvementStock $t) => $sens === null || $t->sens() === $sens)->values(),
        ]);
    }
}
