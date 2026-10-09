<?php

namespace App\Http\Controllers;

use App\Enums\StatutAchat;
use App\Http\Requests\FournisseurRequest;
use App\Models\Achat;
use App\Models\Fournisseur;
use App\Models\Paiement;
use App\Services\JournalService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Fournisseurs (droit fournisseurs.gerer) : liste, panneau de saisie, fiche avec dette et historiques.
 * Un fournisseur ayant des achats ne peut être que désactivé.
 */
class FournisseurController extends Controller
{
    public function __construct(private readonly JournalService $journal) {}

    public function index(Request $requete): View
    {
        $recherche = trim((string) $requete->query('recherche'));
        $statut = in_array($requete->query('statut'), ['inactifs', 'tous'], true) ? $requete->query('statut') : 'actifs';
        $tri = $requete->query('tri') === 'dette' ? 'dette' : 'nom';
        $ordre = $requete->query('ordre') === 'desc' ? 'desc' : 'asc';

        $fournisseurs = Fournisseur::query()
            ->withSum(['achats as dette' => fn ($q) => $q->where('statut', StatutAchat::Valide)], 'reste_a_payer')
            ->withCount('achats') // un fournisseur sans achat peut être supprimé
            ->when($recherche !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('nom', 'like', "%{$recherche}%")
                ->orWhere('contact', 'like', "%{$recherche}%")
                ->orWhere('telephone', 'like', "%{$recherche}%")
                ->orWhere('email', 'like', "%{$recherche}%")))
            ->when($statut === 'actifs', fn ($q) => $q->where('actif', true))
            ->when($statut === 'inactifs', fn ($q) => $q->where('actif', false))
            ->orderBy($tri, $ordre)
            ->orderBy('nom')
            ->paginate(15)
            ->withQueryString();

        $donnees = compact('fournisseurs', 'recherche', 'statut');

        if ($requete->header('X-Fragment') === 'liste') {
            return view('fournisseurs._liste', $donnees);
        }

        $dettes = Achat::valides()->where('reste_a_payer', '>', 0);

        return view('fournisseurs.index', [
            ...$donnees,
            'synthese' => [
                'actifs' => Fournisseur::where('actif', true)->count(),
                'dette' => (float) (clone $dettes)->sum('reste_a_payer'),
                'aRegler' => (clone $dettes)->distinct()->count('fournisseur_id'),
            ],
        ]);
    }

    public function show(Fournisseur $fournisseur): View
    {
        $achatsValides = $fournisseur->achats()->valides();

        return view('fournisseurs.show', [
            'fournisseur' => $fournisseur,
            'dette' => (float) (clone $achatsValides)->sum('reste_a_payer'),
            'achatsNonSoldes' => (clone $achatsValides)->where('reste_a_payer', '>', 0)->count(),
            'totalAchete' => (float) (clone $achatsValides)->sum('total'),
            'nombreAchats' => (clone $achatsValides)->count(),
            'achats' => $fournisseur->achats()->latest('date_achat')->latest('id')->paginate(10, ['*'], 'page_achats'),
            'paiements' => Paiement::whereHasMorph('payable', [Achat::class], fn ($q) => $q->where('fournisseur_id', $fournisseur->id))
                ->with('payable', 'utilisateur')
                ->latest('date_paiement')
                ->limit(15)
                ->get(),
        ]);
    }

    public function store(FournisseurRequest $requete): RedirectResponse
    {
        $fournisseur = Fournisseur::create($requete->validated());

        return to_route('fournisseurs.index')->with('succes', "Fournisseur « {$fournisseur->nom} » créé.");
    }

    public function update(FournisseurRequest $requete, Fournisseur $fournisseur): RedirectResponse
    {
        $fournisseur->update($requete->validated());

        return back()->with('succes', "Fournisseur « {$fournisseur->nom} » modifié.");
    }

    public function statut(Fournisseur $fournisseur): RedirectResponse
    {
        $fournisseur->update(['actif' => ! $fournisseur->actif]);
        $this->journal->enregistrer($fournisseur->actif ? 'fournisseur.reactive' : 'fournisseur.desactive', $fournisseur, ['nom' => $fournisseur->nom]);

        return back()->with('succes', $fournisseur->actif
            ? "Fournisseur « {$fournisseur->nom} » réactivé."
            : "Fournisseur « {$fournisseur->nom} » désactivé : il n'est plus proposé pour les nouveaux achats.");
    }

    public function destroy(Fournisseur $fournisseur): RedirectResponse
    {
        if ($fournisseur->achats()->exists()) {
            return back()->with('erreur', "Le fournisseur « {$fournisseur->nom} » a des achats enregistrés : il ne peut pas être supprimé. Désactivez-le plutôt.");
        }

        $fournisseur->delete();
        $this->journal->enregistrer('fournisseur.supprime', $fournisseur, ['nom' => $fournisseur->nom]);

        return to_route('fournisseurs.index')->with('succes', "Fournisseur « {$fournisseur->nom} » supprimé.");
    }
}
