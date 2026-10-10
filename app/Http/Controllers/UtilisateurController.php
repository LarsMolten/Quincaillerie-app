<?php

namespace App\Http\Controllers;

use App\Exceptions\OperationRefuseeException;
use App\Http\Requests\MotDePasseRequest;
use App\Http\Requests\UtilisateurRequest;
use App\Models\JournalActivite;
use App\Models\Role;
use App\Models\Utilisateur;
use App\Services\UtilisateurService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Administration > Utilisateurs (droit utilisateurs.gerer) : liste avec recherche et filtres,
 * panneau de création / modification, activation, réinitialisation du mot de passe, suppression douce.
 */
class UtilisateurController extends Controller
{
    public function __construct(private readonly UtilisateurService $utilisateurs) {}

    public function index(Request $requete): View
    {
        $recherche = trim((string) $requete->query('recherche'));
        $statut = in_array($requete->query('statut'), ['inactifs', 'tous'], true) ? $requete->query('statut') : 'actifs';
        $roleId = Role::whereKey($requete->integer('role'))->value('id');

        $utilisateurs = Utilisateur::query()
            ->with('role')
            // Dernière connexion réussie (journal)
            ->addSelect(['derniere_connexion' => JournalActivite::query()
                ->select('created_at')
                ->whereColumn('utilisateur_id', 'utilisateurs.id')
                ->where('action', 'connexion')
                ->latest('id')
                ->limit(1)])
            ->when($recherche !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('nom', 'like', "%{$recherche}%")
                ->orWhere('email', 'like', "%{$recherche}%")
                ->orWhere('telephone', 'like', "%{$recherche}%")))
            ->when($statut === 'actifs', fn ($q) => $q->where('actif', true))
            ->when($statut === 'inactifs', fn ($q) => $q->where('actif', false))
            ->when($roleId, fn ($q, int $id) => $q->where('role_id', $id))
            ->orderBy('nom')
            ->paginate(15)
            ->withQueryString();

        $donnees = compact('utilisateurs', 'recherche', 'statut', 'roleId');

        if ($requete->header('X-Fragment') === 'liste') {
            return view('utilisateurs._liste', $donnees);
        }

        return view('utilisateurs.index', [
            ...$donnees,
            'roles' => Role::orderBy('nom')->pluck('nom', 'id'),
            'synthese' => [
                'actifs' => Utilisateur::where('actif', true)->count(),
                'administrateurs' => Utilisateur::where('actif', true)->whereHas('role', fn ($q) => $q->where('nom', Role::ADMINISTRATEUR))->count(),
                'inactifs' => Utilisateur::where('actif', false)->count(),
            ],
        ]);
    }

    public function store(UtilisateurRequest $requete): RedirectResponse
    {
        $utilisateur = $this->utilisateurs->creer($requete->validated());

        return to_route('utilisateurs.index')->with('succes', "Compte de « {$utilisateur->nom} » créé ({$utilisateur->role->nom}).");
    }

    public function update(UtilisateurRequest $requete, Utilisateur $utilisateur): RedirectResponse
    {
        return $this->executer(
            fn () => $this->utilisateurs->modifier($utilisateur, $requete->validated(), $requete->user()),
            "Compte de « {$utilisateur->nom} » modifié.",
        );
    }

    public function statut(Request $requete, Utilisateur $utilisateur): RedirectResponse
    {
        return $this->executer(
            fn () => $this->utilisateurs->basculerStatut($utilisateur, $requete->user()),
            fn () => $utilisateur->actif
                ? "Compte de « {$utilisateur->nom} » réactivé."
                : "Compte de « {$utilisateur->nom} » désactivé : il ne peut plus se connecter.",
        );
    }

    public function motDePasse(MotDePasseRequest $requete, Utilisateur $utilisateur): RedirectResponse
    {
        $this->utilisateurs->reinitialiserMotDePasse($utilisateur, $requete->validated('password'));

        return back()->with('succes', "Mot de passe de « {$utilisateur->nom} » réinitialisé : communiquez-le-lui, il pourra se connecter avec.");
    }

    public function destroy(Request $requete, Utilisateur $utilisateur): RedirectResponse
    {
        return $this->executer(
            fn () => $this->utilisateurs->supprimer($utilisateur, $requete->user()),
            "Compte de « {$utilisateur->nom} » supprimé.",
        );
    }

    /** Exécute une opération du service ; une règle de sécurité refusée devient un toast d'erreur. */
    private function executer(callable $operation, string|callable $succes): RedirectResponse
    {
        try {
            $operation();
        } catch (OperationRefuseeException $erreur) {
            return back()->withInput()->with('erreur', $erreur->getMessage());
        }

        return back()->with('succes', is_callable($succes) ? $succes() : $succes);
    }
}
