<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategorieRequest;
use App\Models\Categorie;
use App\Models\Produit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Catégories de produits (droit categories.gerer).
 * Création et modification en modale ; une catégorie utilisée ne peut être que désactivée.
 */
class CategorieController extends Controller
{
    public function index(Request $requete): View
    {
        $recherche = trim((string) $requete->query('recherche'));
        $statut = $requete->query('statut');
        $tri = $requete->query('tri') === 'produits' ? 'produits_count' : 'nom';
        $ordre = $requete->query('ordre') === 'desc' ? 'desc' : 'asc';

        $categories = Categorie::query()
            ->withCount('produits')
            ->when($recherche !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('nom', 'like', "%{$recherche}%")
                ->orWhere('description', 'like', "%{$recherche}%")))
            ->when($statut === 'actives', fn ($q) => $q->where('actif', true))
            ->when($statut === 'inactives', fn ($q) => $q->where('actif', false))
            ->orderBy($tri, $ordre)
            ->orderBy('nom')
            ->paginate(12)
            ->withQueryString();

        // Recherche instantanée : seule la liste est renvoyée
        if ($requete->header('X-Fragment') === 'liste') {
            return view('categories._liste', compact('categories', 'recherche', 'statut'));
        }

        $synthese = [
            'actives' => Categorie::where('actif', true)->count(),
            'inactives' => Categorie::where('actif', false)->count(),
            'produits' => Produit::count(), // tout produit a une catégorie
            'vides' => Categorie::doesntHave('produits')->count(),
        ];

        return view('categories.index', compact('categories', 'recherche', 'statut', 'synthese'));
    }

    public function store(CategorieRequest $requete): RedirectResponse
    {
        $donnees = $requete->validated();

        // Une catégorie supprimée de même nom est restaurée plutôt que recréée (nom unique en base)
        $supprimee = Categorie::onlyTrashed()->where('nom', $donnees['nom'])->first();

        if ($supprimee) {
            $supprimee->restore();
            $supprimee->update($donnees);

            return to_route('categories.index')->with('succes', "Catégorie « {$supprimee->nom} » restaurée.");
        }

        $categorie = Categorie::create($donnees);

        return to_route('categories.index')->with('succes', "Catégorie « {$categorie->nom} » créée.");
    }

    public function update(CategorieRequest $requete, Categorie $categorie): RedirectResponse
    {
        $categorie->update($requete->validated());

        return back()->with('succes', "Catégorie « {$categorie->nom} » modifiée.");
    }

    /** Active ou désactive la catégorie (seule action possible si elle est utilisée). */
    public function statut(Categorie $categorie): RedirectResponse
    {
        $categorie->update(['actif' => ! $categorie->actif]);

        return back()->with('succes', $categorie->actif
            ? "Catégorie « {$categorie->nom} » réactivée."
            : "Catégorie « {$categorie->nom} » désactivée : elle n'est plus proposée pour les nouveaux produits.");
    }

    public function destroy(Categorie $categorie): RedirectResponse
    {
        // Les produits supprimés comptent aussi : ils gardent leur historique de ventes
        $nombre = $categorie->produits()->withTrashed()->count();

        if ($nombre > 0) {
            return back()->with('erreur', sprintf(
                'La catégorie « %s » est utilisée par %d produit%s : elle ne peut pas être supprimée. Désactivez-la plutôt.',
                $categorie->nom,
                $nombre,
                $nombre > 1 ? 's' : '',
            ));
        }

        $categorie->delete();

        return back()->with('succes', "Catégorie « {$categorie->nom} » supprimée.");
    }
}
