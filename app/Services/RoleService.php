<?php

namespace App\Services;

use App\Exceptions\OperationRefuseeException;
use App\Models\Droit;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

/**
 * Rôles et droits (Administration > Rôles et droits, droit roles.gerer).
 *
 * - L'Administrateur a toujours tous les droits : sa matrice n'est pas modifiable (Gate::before) ;
 * - les 4 rôles livrés ne se renomment ni ne se suppriment (le code les référence par leur nom) ;
 * - un rôle personnalisé se supprime seulement s'il n'a jamais eu de compte (même désactivé ou supprimé).
 * Création, modification et suppression : trait Journalisable ; droits : entrée « role.droits_modifies ».
 */
class RoleService
{
    public function __construct(private readonly JournalService $journal) {}

    /**
     * @param  array{nom: string, description: ?string, modele_id?: ?int}  $donnees
     */
    public function creer(array $donnees): Role
    {
        return DB::transaction(function () use ($donnees) {
            $role = Role::create(['nom' => $donnees['nom'], 'description' => $donnees['description'] ?? null]);

            // Point de départ facultatif : les droits d'un rôle existant
            if (! empty($donnees['modele_id'])) {
                $modele = Role::findOrFail($donnees['modele_id']);
                $codes = $modele->estAdministrateur() ? Droit::pluck('code')->all() : $modele->droits()->pluck('code')->all();
                $this->synchroniserDroits($role, $codes);
            }

            return $role;
        });
    }

    /** @param  array{nom: string, description: ?string}  $donnees */
    public function modifier(Role $role, array $donnees): Role
    {
        if ($role->estParDefaut() && $donnees['nom'] !== $role->nom) {
            throw new OperationRefuseeException("Le rôle « {$role->nom} » est livré avec l'application : il ne peut pas être renommé.");
        }

        $role->update(['nom' => $donnees['nom'], 'description' => $donnees['description'] ?? null]);

        return $role;
    }

    /**
     * Remplace les droits du rôle par $codes et journalise les droits ajoutés et retirés.
     *
     * @param  list<string>  $codes
     * @return array{ajoutes: list<string>, retires: list<string>}
     */
    public function synchroniserDroits(Role $role, array $codes): array
    {
        if ($role->estAdministrateur()) {
            throw new OperationRefuseeException('L\'Administrateur a toujours tous les droits : ils ne sont pas modifiables.');
        }

        return DB::transaction(function () use ($role, $codes) {
            $avant = $role->droits()->pluck('code')->all();
            $nouveaux = Droit::whereIn('code', $codes)->get();

            $role->droits()->sync($nouveaux->pluck('id'));
            $role->unsetRelation('droits');

            $apres = $nouveaux->pluck('code')->all();
            $ecart = [
                'ajoutes' => array_values(array_diff($apres, $avant)),
                'retires' => array_values(array_diff($avant, $apres)),
            ];

            if ($ecart['ajoutes'] || $ecart['retires']) {
                $this->journal->enregistrer('role.droits_modifies', $role, ['objet' => $role->nom, ...array_filter($ecart)]);
            }

            return $ecart;
        });
    }

    public function supprimer(Role $role): void
    {
        if ($role->estParDefaut()) {
            throw new OperationRefuseeException("Le rôle « {$role->nom} » est livré avec l'application : il ne peut pas être supprimé.");
        }

        DB::transaction(function () use ($role) {
            // Les comptes supprimés gardent leur rôle (historique) : ils comptent aussi
            $comptes = $role->utilisateurs()->withTrashed()->lockForUpdate()->count();
            if ($comptes > 0) {
                throw new OperationRefuseeException(sprintf(
                    'Le rôle « %s » est attribué à %d compte%s (actifs, désactivés ou supprimés) : il ne peut pas être supprimé.',
                    $role->nom, $comptes, $comptes > 1 ? 's' : '',
                ));
            }

            // Rôle de configuration jamais utilisé : suppression réelle (ses droits partent avec, role_droit en cascade)
            $role->delete();
        });
    }
}
