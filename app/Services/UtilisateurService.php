<?php

namespace App\Services;

use App\Exceptions\OperationRefuseeException;
use App\Models\Parametre;
use App\Models\Role;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\DB;

/**
 * Comptes utilisateurs (Administration > Utilisateurs, droit utilisateurs.gerer).
 *
 * Règles de sécurité :
 * - personne ne peut se désactiver, se supprimer ni changer son propre rôle ;
 * - le dernier administrateur actif ne peut être ni désactivé, ni supprimé, ni changer de rôle
 *   (vérification sous verrou, pour deux administrateurs agissant en même temps) ;
 * - un compte désactivé, supprimé ou dont le mot de passe est réinitialisé perd ses sessions ouvertes.
 * Créations, modifications, désactivations et suppressions sont journalisées (trait Journalisable).
 */
class UtilisateurService
{
    public function __construct(private readonly JournalService $journal) {}

    /** @param  array<string, mixed>  $donnees  données validées (UtilisateurRequest) */
    public function creer(array $donnees): Utilisateur
    {
        $theme = Parametre::valeur('theme_defaut', 'auto');

        return Utilisateur::create([
            ...$donnees,
            // Thème des nouveaux comptes : celui choisi dans Paramètres > Apparence
            'preference_theme' => in_array($theme, ['clair', 'sombre', 'auto'], true) ? $theme : 'auto',
        ]);
    }

    /** @param  array<string, mixed>  $donnees */
    public function modifier(Utilisateur $cible, array $donnees, Utilisateur $auteur): Utilisateur
    {
        return DB::transaction(function () use ($cible, $donnees, $auteur) {
            $changeDeRole = (int) $donnees['role_id'] !== (int) $cible->role_id;

            if ($changeDeRole && $cible->is($auteur)) {
                throw new OperationRefuseeException('Vous ne pouvez pas changer votre propre rôle.');
            }
            if ($changeDeRole && $this->estDernierAdministrateur($cible)) {
                throw new OperationRefuseeException('C\'est le dernier administrateur actif : il doit garder ce rôle.');
            }

            $cible->update($donnees);

            return $cible;
        });
    }

    public function basculerStatut(Utilisateur $cible, Utilisateur $auteur): Utilisateur
    {
        return DB::transaction(function () use ($cible, $auteur) {
            if ($cible->actif) {
                if ($cible->is($auteur)) {
                    throw new OperationRefuseeException('Vous ne pouvez pas désactiver votre propre compte.');
                }
                if ($this->estDernierAdministrateur($cible)) {
                    throw new OperationRefuseeException('Impossible de désactiver le dernier administrateur actif.');
                }
            }

            $cible->update(['actif' => ! $cible->actif]);
            if (! $cible->actif) {
                $this->fermerSessions($cible);
            }

            return $cible;
        });
    }

    public function supprimer(Utilisateur $cible, Utilisateur $auteur): void
    {
        DB::transaction(function () use ($cible, $auteur) {
            if ($cible->is($auteur)) {
                throw new OperationRefuseeException('Vous ne pouvez pas supprimer votre propre compte.');
            }
            if ($this->estDernierAdministrateur($cible)) {
                throw new OperationRefuseeException('Impossible de supprimer le dernier administrateur actif.');
            }

            // Suppression douce : ventes, achats et journal gardent leur auteur
            $cible->delete();
            $this->fermerSessions($cible);
        });
    }

    public function reinitialiserMotDePasse(Utilisateur $cible, string $motDePasse): void
    {
        DB::transaction(function () use ($cible, $motDePasse) {
            // Hors trait (saveQuietly) : une seule entrée explicite, sans le mot de passe
            $cible->forceFill(['password' => $motDePasse, 'remember_token' => null])->saveQuietly();
            $this->fermerSessions($cible);
            $this->journal->enregistrer('utilisateur.mot_de_passe_reinitialise', $cible, ['objet' => $cible->nom]);
        });
    }

    /** Le compte est-il le seul administrateur actif ? (lecture verrouillée, dans la transaction) */
    private function estDernierAdministrateur(Utilisateur $cible): bool
    {
        if (! $cible->actif || ! $cible->estAdministrateur()) {
            return false;
        }

        $administrateurs = Utilisateur::query()
            ->where('actif', true)
            ->whereHas('role', fn ($q) => $q->where('nom', Role::ADMINISTRATEUR))
            ->lockForUpdate()
            ->pluck('id');

        return $administrateurs->count() <= 1;
    }

    /** Déconnecte le compte sur ses autres appareils (sessions en base), sauf la session en cours. */
    private function fermerSessions(Utilisateur $cible): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $cible->getKey())
                ->where('id', '!=', session()->getId())
                ->delete();
        }
    }
}
