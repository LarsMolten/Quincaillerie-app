<?php

namespace App\Http\Controllers;

use App\Http\Requests\UniteRequest;
use App\Models\Unite;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Unités de vente (droit categories.gerer).
 * Création et modification en modale ; une unité utilisée par des produits ne peut pas être supprimée.
 */
class UniteController extends Controller
{
    public function index(Request $requete): View
    {
        $recherche = trim((string) $requete->query('recherche'));
        $tri = $requete->query('tri') === 'produits' ? 'produits_count' : 'nom';
        $ordre = $requete->query('ordre') === 'desc' ? 'desc' : 'asc';

        $unites = Unite::query()
            ->withCount('produits')
            ->when($recherche !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('nom', 'like', "%{$recherche}%")
                ->orWhere('abreviation', 'like', "%{$recherche}%")))
            ->orderBy($tri, $ordre)
            ->orderBy('nom')
            ->paginate(15)
            ->withQueryString();

        // Recherche instantanée : seule la liste est renvoyée
        if ($requete->header('X-Fragment') === 'liste') {
            return view('unites._liste', compact('unites', 'recherche'));
        }

        return view('unites.index', compact('unites', 'recherche'));
    }

    public function store(UniteRequest $requete): RedirectResponse
    {
        $donnees = $requete->validated();

        // Une unité supprimée portant ce nom ou cette abréviation est restaurée (valeurs uniques en base)
        $supprimee = Unite::onlyTrashed()
            ->where(fn ($q) => $q->where('nom', $donnees['nom'])->orWhere('abreviation', $donnees['abreviation']))
            ->first();

        if ($supprimee) {
            $supprimee->restore();
            $supprimee->update($donnees);

            return to_route('unites.index')->with('succes', "Unité « {$supprimee->nom} » restaurée.");
        }

        $unite = Unite::create($donnees);

        return to_route('unites.index')->with('succes', "Unité « {$unite->nom} » créée.");
    }

    public function update(UniteRequest $requete, Unite $unite): RedirectResponse
    {
        $unite->update($requete->validated());

        return back()->with('succes', "Unité « {$unite->nom} » modifiée.");
    }

    public function destroy(Unite $unite): RedirectResponse
    {
        $nombre = $unite->produits()->withTrashed()->count();

        if ($nombre > 0) {
            return back()->with('erreur', sprintf(
                'L\'unité « %s » est utilisée par %d produit%s : elle ne peut pas être supprimée. Modifiez d\'abord ces produits.',
                $unite->nom,
                $nombre,
                $nombre > 1 ? 's' : '',
            ));
        }

        $unite->delete();

        return back()->with('succes', "Unité « {$unite->nom} » supprimée.");
    }
}
