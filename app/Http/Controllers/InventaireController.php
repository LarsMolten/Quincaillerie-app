<?php

namespace App\Http\Controllers;

use App\Enums\StatutInventaire;
use App\Exceptions\OperationRefuseeException;
use App\Exceptions\StockInsuffisantException;
use App\Models\Categorie;
use App\Models\Inventaire;
use App\Models\LigneInventaire;
use App\Services\InventaireService;
use App\Support\ReponsePdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Inventaires physiques (droit inventaires.gerer) : liste, ouverture, saisie du comptage (tablette),
 * import CSV, validation, feuille de comptage et rapport d'écarts en PDF.
 */
class InventaireController extends Controller
{
    public function __construct(private readonly InventaireService $inventaires) {}

    public function index(Request $requete): View
    {
        $statut = in_array($requete->query('statut'), ['en_cours', 'valide'], true) ? $requete->query('statut') : null;

        $inventaires = Inventaire::query()->avecProgression()->with('utilisateur')
            ->when($statut, fn ($q, string $s) => $q->where('statut', $s))
            ->latest('date_inventaire')->latest('id')
            ->paginate(15)->withQueryString();

        if ($requete->header('X-Fragment') === 'liste') {
            return view('inventaires._liste', compact('inventaires', 'statut'));
        }

        return view('inventaires.index', [
            'inventaires' => $inventaires,
            'statut' => $statut,
            'categories' => Categorie::orderBy('nom')->withCount(['produits' => fn ($q) => $q->where('actif', true)])->get(['id', 'nom']),
        ]);
    }

    public function store(Request $requete): RedirectResponse
    {
        $donnees = $requete->validate([
            'perimetre' => ['required', Rule::in(['tous', 'categories'])],
            'categories' => ['exclude_unless:perimetre,categories', 'required', 'array', 'min:1'],
            'categories.*' => ['integer', 'exists:categories,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'categories.required' => 'Cochez au moins une catégorie.',
        ]);

        try {
            $inventaire = $this->inventaires->ouvrir($donnees['categories'] ?? null, $donnees['notes'] ?? null);
        } catch (OperationRefuseeException $erreur) {
            return back()->withInput()->with('erreur', $erreur->getMessage());
        }

        return to_route('inventaires.show', $inventaire)
            ->with('succes', "Inventaire {$inventaire->numero} ouvert : {$inventaire->lignes()->count()} produit(s) à compter.");
    }

    /** Saisie du comptage (lecture seule une fois validé). */
    public function show(Inventaire $inventaire): View
    {
        $inventaire->load(['utilisateur', 'lignes.produit.unite', 'lignes.produit.categorie']);
        $valide = $inventaire->statut === StatutInventaire::Valide;

        $lignes = $inventaire->lignes
            ->sortBy(fn (LigneInventaire $l) => mb_strtolower(($l->produit->categorie?->nom ?? '').' '.$l->produit->nom))
            ->values()
            ->map(fn (LigneInventaire $l) => [
                'id' => $l->id,
                'nom' => $l->produit->nom,
                'reference' => $l->produit->reference,
                'code_barres' => (string) $l->produit->code_barres,
                'categorie' => $l->produit->categorie?->nom ?? 'Sans catégorie',
                'unite' => $l->produit->unite?->abreviation,
                'theorique' => (float) $l->stock_theorique,
                'actuel' => (float) $l->produit->stock_actuel,
                'compte' => $l->stock_compte === null ? null : (float) $l->stock_compte,
                'ecart' => $l->ecart === null ? null : (float) $l->ecart,
            ]);

        return view('inventaires.show', [
            'inventaire' => $inventaire,
            'valide' => $valide,
            'config' => [
                'lignes' => $lignes,
                'lectureSeule' => $valide,
                'urlLigne' => route('inventaires.lignes.update', [$inventaire, '__LIGNE__']),
            ],
        ]);
    }

    /** Comptage d'une ligne (JSON) : quantité, ou vide pour remettre à « non compté ». */
    public function compter(Request $requete, Inventaire $inventaire, LigneInventaire $ligne): JsonResponse
    {
        $requete->merge(['quantite' => filled($requete->input('quantite')) ? str_replace([' ', "\u{00A0}", ','], ['', '', '.'], (string) $requete->input('quantite')) : null]);
        $requete->validate(
            ['quantite' => ['nullable', 'numeric', 'min:0', 'max:9999999']],
            ['quantite.min' => 'La quantité comptée ne peut pas être négative.', 'quantite.numeric' => 'Saisissez un nombre.'],
        );

        try {
            $ligne = $this->inventaires->compter($inventaire, $ligne, $requete->filled('quantite') ? (float) $requete->input('quantite') : null);
        } catch (OperationRefuseeException $erreur) {
            return response()->json(['message' => $erreur->getMessage(), 'errors' => ['quantite' => [$erreur->getMessage()]]], 422);
        }

        return response()->json([
            'compte' => $ligne->stock_compte === null ? null : (float) $ligne->stock_compte,
            'ecart' => $ligne->ecart === null ? null : (float) $ligne->ecart,
            'actuel' => (float) $ligne->produit->stock_actuel,
        ]);
    }

    public function importer(Request $requete, Inventaire $inventaire): RedirectResponse
    {
        $requete->validate(
            ['fichier' => ['required', 'file', 'mimes:csv,txt', 'max:2048']],
            ['fichier.required' => 'Choisissez un fichier CSV.', 'fichier.mimes' => 'Le fichier doit être au format CSV.'],
        );

        try {
            $bilan = $this->inventaires->importerCsv($inventaire, $requete->file('fichier'));
        } catch (OperationRefuseeException $erreur) {
            return back()->with('erreur', $erreur->getMessage());
        }

        $message = "{$bilan['importees']} comptage(s) importé(s).";
        $problemes = array_filter([
            $bilan['inconnues'] ? count($bilan['inconnues']).' référence(s) hors inventaire : '.implode(', ', array_slice($bilan['inconnues'], 0, 5)) : null,
            $bilan['invalides'] ? count($bilan['invalides']).' quantité(s) invalide(s) : '.implode(', ', array_slice($bilan['invalides'], 0, 5)) : null,
        ]);

        return back()->with($problemes ? 'alerte' : 'succes', trim($message.' '.implode(' · ', $problemes)));
    }

    public function valider(Request $requete, Inventaire $inventaire): RedirectResponse
    {
        try {
            $this->inventaires->valider($inventaire, $requete->boolean('confirmer_non_comptes'));
        } catch (OperationRefuseeException|StockInsuffisantException $erreur) {
            return back()->with('erreur', $erreur->getMessage());
        }

        return to_route('inventaires.show', $inventaire)
            ->with('succes', "Inventaire {$inventaire->numero} validé : stock corrigé.")
            ->with('toast_lien', ['libelle' => 'Rapport d\'écarts', 'url' => route('inventaires.pdf', [$inventaire, 'rapport']), 'nouvelOnglet' => true, 'pdf' => true]);
    }

    /** Feuille de comptage ou rapport d'écarts (PDF). */
    public function pdf(Request $requete, Inventaire $inventaire, string $document): Response
    {
        abort_unless(in_array($document, ['feuille', 'rapport'], true), 404);

        return ReponsePdf::depuis($requete, $this->inventaires->pdf($inventaire, $document), "{$document}-{$inventaire->numero}.pdf");
    }
}
