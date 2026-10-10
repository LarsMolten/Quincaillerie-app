<?php

namespace App\Http\Controllers;

use App\Enums\StatutAchat;
use App\Enums\StatutVente;
use App\Http\Requests\ProduitRequest;
use App\Models\Categorie;
use App\Models\Parametre;
use App\Models\Produit;
use App\Models\Unite;
use App\Services\PhotoProduitService;
use App\Services\ProduitService;
use App\Support\Ean13;
use App\Support\ReponsePdf;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Picqer\Barcode\BarcodeGeneratorSVG;
use Symfony\Component\HttpFoundation\Response;

/**
 * Catalogue des produits : liste (tableau ou grille), panneau de création/modification,
 * fiche « bento », désactivation (jamais de suppression) et étiquettes PDF.
 */
class ProduitController extends Controller
{
    private const TRIS = ['nom', 'reference', 'prix_vente', 'stock_actuel', 'categorie'];

    public function __construct(private readonly ProduitService $produits) {}

    public function index(Request $requete): View
    {
        // Vue tableau ou grille, mémorisée pour la session
        if (in_array($requete->query('vue'), ['tableau', 'grille'], true)) {
            $requete->session()->put('produits.vue', $requete->query('vue'));
        }
        $vue = $requete->session()->get('produits.vue', 'tableau');

        $filtres = [
            'recherche' => trim((string) $requete->query('recherche')),
            'categorie' => $requete->integer('categorie') ?: null,
            'statut' => in_array($requete->query('statut'), ['actifs', 'inactifs', 'tous'], true) ? $requete->query('statut') : 'actifs',
            'stock' => in_array($requete->query('stock'), ['normal', 'faible', 'rupture'], true) ? $requete->query('stock') : null,
        ];
        $tri = in_array($requete->query('tri'), self::TRIS, true) ? $requete->query('tri') : 'nom';
        $ordre = $requete->query('ordre') === 'desc' ? 'desc' : 'asc';

        $produits = Produit::query()
            ->select('produits.*')
            ->with(['categorie', 'unite'])
            ->recherche($filtres['recherche'])
            ->when($filtres['categorie'], fn ($q, $id) => $q->where('categorie_id', $id))
            ->when($filtres['statut'] === 'actifs', fn ($q) => $q->where('produits.actif', true))
            ->when($filtres['statut'] === 'inactifs', fn ($q) => $q->where('produits.actif', false))
            ->when($filtres['stock'] === 'normal', fn ($q) => $q->stockNormal())
            ->when($filtres['stock'] === 'faible', fn ($q) => $q->stockFaible())
            ->when($filtres['stock'] === 'rupture', fn ($q) => $q->enRupture())
            ->when($tri === 'categorie',
                fn ($q) => $q->join('categories', 'categories.id', '=', 'produits.categorie_id')->orderBy('categories.nom', $ordre),
                fn ($q) => $q->orderBy("produits.{$tri}", $ordre))
            ->orderBy('produits.nom')
            ->paginate($vue === 'grille' ? 12 : 15)
            ->withQueryString();

        $donnees = compact('produits', 'filtres', 'vue');

        // Recherche instantanée : seule la liste est renvoyée
        if ($requete->header('X-Fragment') === 'liste') {
            return view('produits._liste', $donnees);
        }

        return view('produits.index', [
            ...$donnees,
            ...$this->donneesFormulaire(),
            'categoriesFiltre' => Categorie::orderBy('nom')->get(['id', 'nom']),
            'synthese' => [
                'actifs' => Produit::actif()->count(),
                'faibles' => Produit::actif()->stockFaible()->count(),
                'ruptures' => Produit::actif()->enRupture()->count(),
                'valeur' => (float) Produit::actif()->where('stock_actuel', '>', 0)->sum(DB::raw('stock_actuel * prix_achat')),
            ],
        ]);
    }

    public function store(ProduitRequest $requete): RedirectResponse
    {
        $produit = $this->produits->creer(
            $requete->donnees(),
            $requete->file('photo'),
            (float) $requete->validated('stock_initial', 0),
        );

        return $this->retour(to_route('produits.index'), $produit, "Produit « {$produit->nom} » créé ({$produit->reference}).");
    }

    public function update(ProduitRequest $requete, Produit $produit): RedirectResponse
    {
        $this->produits->modifier($produit, $requete->donnees(), $requete->file('photo'), $requete->boolean('retirer_photo'));

        return $this->retour(back(), $produit, "Produit « {$produit->nom} » modifié.");
    }

    public function show(Produit $produit): View
    {
        $produit->load(['categorie', 'unite']);

        // Quantités vendues par jour (ventes validées) sur 30 jours, et les 30 jours précédents
        $debut = today()->subDays(29);
        $parJour = $produit->lignesVente()
            ->join('ventes', 'ventes.id', '=', 'lignes_vente.vente_id')
            ->where('ventes.statut', StatutVente::Validee)
            ->where('ventes.date_vente', '>=', $debut)
            ->selectRaw('DATE(ventes.date_vente) as jour, SUM(lignes_vente.quantite) as quantite')
            ->groupBy('jour')
            ->pluck('quantite', 'jour');
        $serie = collect(range(0, 29))->map(fn (int $i) => (float) ($parJour[$debut->copy()->addDays($i)->toDateString()] ?? 0))->all();
        $vendu30 = array_sum($serie);
        $vendu30Precedents = (float) $produit->lignesVente()
            ->join('ventes', 'ventes.id', '=', 'lignes_vente.vente_id')
            ->where('ventes.statut', StatutVente::Validee)
            ->whereBetween('ventes.date_vente', [$debut->copy()->subDays(30), $debut])
            ->sum('lignes_vente.quantite');

        return view('produits.show', [
            'produit' => $produit,
            'serie' => $serie,
            'vendu30' => $vendu30,
            'tendance' => $vendu30Precedents > 0 ? round(($vendu30 - $vendu30Precedents) / $vendu30Precedents * 100, 1) : null,
            'mouvements' => $produit->mouvementsStock()->with('utilisateur')->latest('id')->limit(15)->get(),
            'dernieresVentes' => $produit->lignesVente()->with('vente')
                ->whereHas('vente', fn ($q) => $q->where('statut', StatutVente::Validee))
                ->latest('id')->limit(5)->get(),
            'derniersAchats' => $produit->lignesAchat()->with('achat.fournisseur')
                ->whereHas('achat', fn ($q) => $q->where('statut', StatutAchat::Valide))
                ->latest('id')->limit(5)->get(),
            'codeBarresSvg' => $produit->code_barres ? $this->dessinerCodeBarres($produit->code_barres, 'svg') : null,
            ...$this->donneesFormulaire(),
        ]);
    }

    public function statut(Produit $produit): RedirectResponse
    {
        $this->produits->basculerStatut($produit);

        return back()->with('succes', $produit->actif
            ? "Produit « {$produit->nom} » réactivé."
            : "Produit « {$produit->nom} » désactivé : il n'est plus proposé en vente ni en achat.");
    }

    /** Photo du produit (disque privé), mise en cache par le navigateur. */
    public function photo(Produit $produit): Response
    {
        $disque = Storage::disk(PhotoProduitService::DISQUE);
        abort_unless($produit->image && $disque->exists($produit->image), 404);

        return $disque->response($produit->image, null, [
            'Cache-Control' => 'private, max-age=604800',
        ]);
    }

    /** Prochain code EAN-13 interne libre (bouton « Générer »). */
    public function codeBarres(): JsonResponse
    {
        return response()->json(['code' => Ean13::suivantInterne()]);
    }

    /** Planche d'étiquettes A4 (3 × 8) avec code-barres, en PDF. */
    public function etiquettes(Request $requete): Response
    {
        $valide = $requete->validate([
            'produits' => ['required', 'array', 'min:1', 'max:100'],
            'produits.*' => ['integer', 'exists:produits,id'],
            'quantite' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], [
            'produits.required' => 'Sélectionnez au moins un produit.',
            'quantite.max' => 'Au plus 100 étiquettes par produit.',
        ]);

        $quantite = (int) ($valide['quantite'] ?? 1);
        $etiquettes = Produit::with('unite')->whereIn('id', $valide['produits'])->orderBy('nom')->get()
            ->flatMap(fn (Produit $produit) => array_fill(0, $quantite, [
                'produit' => $produit,
                'code' => $produit->code_barres ? $this->dessinerCodeBarres($produit->code_barres, 'png') : null,
            ]));

        $pdf = Pdf::loadView('produits.etiquettes', [
            'pages' => $etiquettes->chunk(24),
            'entreprise' => Parametre::where('cle', 'nom_entreprise')->value('valeur') ?? config('app.name'),
        ])->setPaper('a4');

        return ReponsePdf::depuis($requete, $pdf, 'etiquettes-'.Carbon::now()->format('Ymd-His').'.pdf');
    }

    /** Listes du panneau de création/modification. */
    private function donneesFormulaire(): array
    {
        return [
            'categoriesFormulaire' => Categorie::orderBy('nom')->get(['id', 'nom', 'actif']),
            'unitesFormulaire' => Unite::orderBy('nom')->get(['id', 'nom', 'abreviation']),
            'prochaineReference' => $this->produits->prochaineReference(),
        ];
    }

    /** Redirection avec toast, plus un avertissement si la marge est négative. */
    private function retour(RedirectResponse $redirection, Produit $produit, string $message): RedirectResponse
    {
        $redirection->with('succes', $message);

        if ((float) $produit->prix_vente < (float) $produit->prix_achat) {
            $redirection->with('alerte', 'Attention : le prix de vente est inférieur au prix d\'achat (marge négative).');
        }

        return $redirection;
    }

    /** Code-barres en SVG (écran) ou en PNG base64 (PDF), EAN-13 si possible, sinon Code 128. */
    private function dessinerCodeBarres(string $code, string $format): string
    {
        $type = Ean13::estValide($code) ? 'EAN13' : 'C128';

        if ($format === 'svg') {
            // Couleur du texte courant : le code-barres suit le thème clair ou sombre
            return str_replace('fill="black"', 'fill="currentColor"', (new BarcodeGeneratorSVG)->getBarcode($code, $type, 2, 56, 'black'));
        }

        return base64_encode((new BarcodeGeneratorPNG)->getBarcode($code, $type, 2, 50));
    }
}
