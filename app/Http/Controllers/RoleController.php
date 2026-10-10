<?php

namespace App\Http\Controllers;

use App\Exceptions\OperationRefuseeException;
use App\Http\Requests\RoleRequest;
use App\Models\Droit;
use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Administration > Rôles et droits (droit roles.gerer) : rôles en cartes, matrice des droits par module
 * (interrupteurs), création de rôles personnalisés.
 */
class RoleController extends Controller
{
    public function __construct(private readonly RoleService $roles) {}

    public function index(): View
    {
        $roles = Role::query()
            ->withCount(['droits', 'utilisateurs as comptes_actifs' => fn ($q) => $q->where('actif', true)])
            // Comptes de toute nature (désactivés et supprimés compris) : décide si le rôle est supprimable
            ->withCount(['utilisateurs as comptes' => fn ($q) => $q->withTrashed()])
            ->get()
            // Rôles livrés d'abord (dans leur ordre), puis les rôles personnalisés par nom
            ->sortBy(fn (Role $role) => [array_search($role->nom, Role::PAR_DEFAUT, true) === false ? 1 : 0, array_search($role->nom, Role::PAR_DEFAUT, true), $role->nom])
            ->values();

        return view('roles.index', ['roles' => $roles, 'totalDroits' => Droit::count()]);
    }

    public function show(Role $role): View
    {
        $droits = Droit::all()->groupBy('module');
        $modules = collect(Droit::MODULES)
            ->filter(fn (array $module, string $code) => $droits->has($code))
            ->map(fn (array $module, string $code) => ['libelle' => $module[0], 'icone' => $module[1], 'droits' => $droits[$code]->sortBy('id')->values()]);
        // Modules sans libellé déclaré (droits ajoutés plus tard) : affichés à la fin
        foreach ($droits->keys()->diff($modules->keys()) as $code) {
            $modules[$code] = ['libelle' => ucfirst($code), 'icone' => 'shield', 'droits' => $droits[$code]->values()];
        }

        return view('roles.show', [
            'role' => $role->loadCount('utilisateurs'),
            'modules' => $modules,
            'actifs' => $role->estAdministrateur() ? Droit::pluck('code')->all() : $role->droits()->pluck('code')->all(),
            'totalDroits' => Droit::count(),
        ]);
    }

    public function store(RoleRequest $requete): RedirectResponse
    {
        $role = $this->roles->creer($requete->validated());

        return to_route('roles.show', $role)->with('succes', "Rôle « {$role->nom} » créé : choisissez maintenant ses droits.");
    }

    public function update(RoleRequest $requete, Role $role): RedirectResponse
    {
        try {
            $this->roles->modifier($role, $requete->validated());
        } catch (OperationRefuseeException $erreur) {
            return back()->withInput()->with('erreur', $erreur->getMessage());
        }

        return back()->with('succes', "Rôle « {$role->nom} » modifié.");
    }

    /** Enregistre la matrice des droits du rôle (codes cochés). */
    public function droits(Request $requete, Role $role): RedirectResponse
    {
        $codes = $requete->validate([
            'droits' => ['array'],
            'droits.*' => ['string', Rule::exists('droits', 'code')],
        ])['droits'] ?? [];

        try {
            $ecart = $this->roles->synchroniserDroits($role, $codes);
        } catch (OperationRefuseeException $erreur) {
            return back()->with('erreur', $erreur->getMessage());
        }

        $message = $ecart['ajoutes'] || $ecart['retires']
            ? sprintf('Droits du rôle « %s » enregistrés : %d ajouté(s), %d retiré(s).', $role->nom, count($ecart['ajoutes']), count($ecart['retires']))
            : "Aucun changement pour le rôle « {$role->nom} ».";

        return back()->with($ecart['ajoutes'] || $ecart['retires'] ? 'succes' : 'info', $message);
    }

    public function destroy(Role $role): RedirectResponse
    {
        try {
            $this->roles->supprimer($role);
        } catch (OperationRefuseeException $erreur) {
            return back()->with('erreur', $erreur->getMessage());
        }

        return to_route('roles.index')->with('succes', "Rôle « {$role->nom} » supprimé.");
    }
}
