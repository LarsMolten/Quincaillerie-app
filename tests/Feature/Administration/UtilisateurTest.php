<?php

namespace Tests\Feature\Administration;

use App\Models\JournalActivite;
use App\Models\Parametre;
use App\Models\Role;
use App\Models\Utilisateur;
use Database\Seeders\DroitSeeder;
use Database\Seeders\ParametreSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Administration > Utilisateurs : CRUD, rôle, activation, réinitialisation du mot de passe,
 * protections (propre compte, dernier administrateur).
 */
class UtilisateurTest extends TestCase
{
    use RefreshDatabase;

    private Utilisateur $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        // Sessions en base, comme en production (fermeture des sessions d'un compte)
        config(['session.driver' => 'database']);
        $this->seed([RoleSeeder::class, DroitSeeder::class, ParametreSeeder::class]);
        $this->admin = $this->compte(Role::ADMINISTRATEUR, ['nom' => 'Rabe Admin']);
        $this->actingAs($this->admin);
    }

    private function compte(string $role, array $attributs = []): Utilisateur
    {
        return Utilisateur::factory()->create(['role_id' => $this->role($role), ...$attributs]);
    }

    private function role(string $nom): int
    {
        return Role::where('nom', $nom)->value('id');
    }

    public function test_liste_recherche_filtres_et_droit(): void
    {
        $this->compte(RoleSeeder::VENDEUR, ['nom' => 'Hery Vendeur', 'email' => 'hery@exemple.mg']);
        $this->compte(RoleSeeder::MAGASINIER, ['nom' => 'Koto Magasin', 'actif' => false]);

        $this->get(route('utilisateurs.index'))->assertOk()
            ->assertSee('Hery Vendeur')->assertSee('Rabe Admin')->assertSee('(vous)')->assertDontSee('Koto Magasin')
            ->assertSee('Nouveau compte')->assertSee('Administrateurs actifs');
        $this->get(route('utilisateurs.index', ['statut' => 'inactifs']))->assertSee('Koto Magasin')->assertDontSee('Hery Vendeur');
        $this->get(route('utilisateurs.index', ['recherche' => 'hery@']), ['X-Fragment' => 'liste'])->assertSee('Hery Vendeur')->assertDontSee('Rabe Admin')->assertDontSee('<html', false);
        $this->get(route('utilisateurs.index', ['role' => $this->role(RoleSeeder::VENDEUR), 'statut' => 'tous']))->assertSee('Hery Vendeur')->assertDontSee('Koto Magasin');

        $this->actingAs($this->compte(RoleSeeder::RESPONSABLE))->get(route('utilisateurs.index'))->assertForbidden();
    }

    public function test_creation_avec_theme_par_defaut_et_journal(): void
    {
        Parametre::where('cle', 'theme_defaut')->update(['valeur' => 'sombre']);
        Parametre::viderCache();

        $this->post(route('utilisateurs.store'), [
            'nom' => '  Rasoa   Caissière ', 'email' => 'Rasoa@Exemple.MG', 'telephone' => '034 11 222 33',
            'role_id' => $this->role(RoleSeeder::VENDEUR), 'password' => 'Caisse2026', 'password_confirmation' => 'Caisse2026', 'actif' => '1',
        ])->assertRedirect(route('utilisateurs.index'))->assertSessionHas('succes', 'Compte de « Rasoa Caissière » créé (Vendeur/Caissier).');

        $compte = Utilisateur::where('email', 'rasoa@exemple.mg')->firstOrFail();
        $this->assertTrue(Hash::check('Caisse2026', $compte->password));
        $this->assertSame('sombre', $compte->preference_theme->value);
        $this->assertTrue($compte->actif);
        $journal = JournalActivite::where('action', 'utilisateur.cree')->where('modele_id', $compte->id)->firstOrFail();
        $this->assertSame($this->admin->id, $journal->utilisateur_id);
        $this->assertStringNotContainsString('Caisse2026', json_encode($journal->details));

        // Validation en français
        $this->post(route('utilisateurs.store'), ['nom' => '', 'email' => 'rasoa@exemple.mg', 'role_id' => 999, 'password' => 'court', 'password_confirmation' => 'autre'])
            ->assertSessionHasErrors([
                'nom' => 'Le champ nom est obligatoire.',
                'email' => 'Cette adresse email est déjà utilisée par un autre compte (éventuellement supprimé).',
                'role_id', 'password',
            ]);
    }

    public function test_modification_et_protection_du_propre_role(): void
    {
        $vendeur = $this->compte(RoleSeeder::VENDEUR, ['nom' => 'Hery']);

        $this->put(route('utilisateurs.update', $vendeur), ['nom' => 'Hery Rakoto', 'email' => $vendeur->email, 'role_id' => $this->role(RoleSeeder::MAGASINIER)])
            ->assertSessionHas('succes');
        $this->assertSame([RoleSeeder::MAGASINIER, 'Hery Rakoto'], [$vendeur->fresh()->role->nom, $vendeur->fresh()->nom]);
        $this->assertSame((string) $this->role(RoleSeeder::VENDEUR), (string) JournalActivite::where('action', 'utilisateur.modifie')->latest('id')->first()->details['anciennes']['role_id']);

        // Le mot de passe ne passe pas par la modification
        $this->put(route('utilisateurs.update', $vendeur), ['nom' => 'Hery', 'email' => $vendeur->email, 'role_id' => $vendeur->role_id, 'password' => 'Pirate123'])
            ->assertSessionHasErrors('password');

        // Personne ne change son propre rôle (même avec un autre administrateur actif)
        $this->compte(Role::ADMINISTRATEUR);
        $this->put(route('utilisateurs.update', $this->admin), ['nom' => 'Rabe', 'email' => $this->admin->email, 'role_id' => $this->role(RoleSeeder::VENDEUR)])
            ->assertSessionHas('erreur', 'Vous ne pouvez pas changer votre propre rôle.');
        $this->assertTrue($this->admin->fresh()->estAdministrateur());
    }

    public function test_dernier_administrateur_et_propre_compte_proteges(): void
    {
        // Un gestionnaire (non administrateur) face au seul administrateur actif
        $gestionnaire = Utilisateur::factory()->avecDroits(['utilisateurs.gerer'])->create();
        $this->actingAs($gestionnaire);

        $this->patch(route('utilisateurs.statut', $this->admin))->assertSessionHas('erreur', 'Impossible de désactiver le dernier administrateur actif.');
        $this->delete(route('utilisateurs.destroy', $this->admin))->assertSessionHas('erreur', 'Impossible de supprimer le dernier administrateur actif.');
        $this->put(route('utilisateurs.update', $this->admin), ['nom' => 'Rabe', 'email' => $this->admin->email, 'role_id' => $this->role(RoleSeeder::VENDEUR)])
            ->assertSessionHas('erreur', 'C\'est le dernier administrateur actif : il doit garder ce rôle.');
        $this->assertTrue($this->admin->fresh()->actif);
        $this->assertNotSoftDeleted($this->admin);

        // Soi-même : ni désactivation ni suppression
        $this->patch(route('utilisateurs.statut', $gestionnaire))->assertSessionHas('erreur', 'Vous ne pouvez pas désactiver votre propre compte.');
        $this->delete(route('utilisateurs.destroy', $gestionnaire))->assertSessionHas('erreur', 'Vous ne pouvez pas supprimer votre propre compte.');

        // Avec un second administrateur actif, le premier peut être désactivé
        $this->compte(Role::ADMINISTRATEUR);
        $this->patch(route('utilisateurs.statut', $this->admin))->assertSessionHas('succes', 'Compte de « Rabe Admin » désactivé : il ne peut plus se connecter.');
        $this->assertFalse($this->admin->fresh()->actif);
        $this->assertDatabaseHas('journal_activites', ['action' => 'utilisateur.desactive', 'modele_id' => $this->admin->id, 'utilisateur_id' => $gestionnaire->id]);
    }

    public function test_desactivation_suppression_et_connexion_refusee(): void
    {
        $vendeur = $this->compte(RoleSeeder::VENDEUR, ['nom' => 'Hery', 'email' => 'hery@exemple.mg', 'password' => 'Vente2026']);
        DB::table('sessions')->insert(['id' => 'session-hery', 'user_id' => $vendeur->id, 'payload' => '', 'last_activity' => time()]);

        $this->patch(route('utilisateurs.statut', $vendeur))->assertSessionHas('succes');
        // Ses sessions ouvertes (en base) sont fermées
        $this->assertDatabaseMissing('sessions', ['id' => 'session-hery']);
        $this->patch(route('utilisateurs.statut', $vendeur))->assertSessionHas('succes', 'Compte de « Hery » réactivé.');

        $this->delete(route('utilisateurs.destroy', $vendeur))->assertSessionHas('succes', 'Compte de « Hery » supprimé.');
        $this->assertSoftDeleted($vendeur);
        $this->assertDatabaseHas('journal_activites', ['action' => 'utilisateur.supprime', 'modele_id' => $vendeur->id]);

        auth()->logout();
        $this->post(route('connexion'), ['email' => 'hery@exemple.mg', 'password' => 'Vente2026'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_reinitialisation_du_mot_de_passe(): void
    {
        $vendeur = $this->compte(RoleSeeder::VENDEUR, ['email' => 'hery@exemple.mg', 'password' => 'Ancien2026', 'remember_token' => 'jeton']);
        DB::table('sessions')->insert(['id' => 'session-hery', 'user_id' => $vendeur->id, 'payload' => '', 'last_activity' => time()]);

        $this->put(route('utilisateurs.mot-de-passe', $vendeur), ['password' => 'court', 'password_confirmation' => 'court'])->assertSessionHasErrors('password');
        $this->put(route('utilisateurs.mot-de-passe', $vendeur), ['password' => 'Nouveau2026', 'password_confirmation' => 'Nouveau2026'])
            ->assertSessionHas('succes');

        $vendeur->refresh();
        $this->assertTrue(Hash::check('Nouveau2026', $vendeur->password));
        $this->assertNull($vendeur->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'session-hery']);
        $this->assertSame(1, JournalActivite::where('modele_id', $vendeur->id)->where('action', 'like', 'utilisateur.%')->where('action', '!=', 'utilisateur.cree')->count(), 'Une seule entrée, explicite.');
        $this->assertDatabaseHas('journal_activites', ['action' => 'utilisateur.mot_de_passe_reinitialise', 'modele_id' => $vendeur->id]);

        auth()->logout();
        $this->post(route('connexion'), ['email' => 'hery@exemple.mg', 'password' => 'Nouveau2026'])->assertRedirect(route('accueil'));
    }
}
