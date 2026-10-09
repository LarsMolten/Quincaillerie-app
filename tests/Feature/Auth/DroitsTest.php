<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\Utilisateur;
use Database\Seeders\DroitSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class DroitsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed([RoleSeeder::class, DroitSeeder::class]);
    }

    private function avecRole(string $role, array $attributs = []): Utilisateur
    {
        return Utilisateur::factory()->create([
            'role_id' => Role::where('nom', $role)->firstOrFail()->id,
            ...$attributs,
        ]);
    }

    public function test_un_vendeur_ne_peut_pas_acceder_aux_utilisateurs(): void
    {
        $this->actingAs($this->avecRole(RoleSeeder::VENDEUR))
            ->get(route('utilisateurs.index'))
            ->assertForbidden()
            ->assertSee('Accès refusé')
            ->assertSee('Vous n&#039;avez pas le droit d&#039;accéder à cette page.', false)
            ->assertSee('lang="fr"', false);
    }

    public function test_l_administrateur_accede_a_tout(): void
    {
        $admin = $this->avecRole(Role::ADMINISTRATEUR);

        $this->actingAs($admin)->get(route('utilisateurs.index'))->assertOk()->assertSee('Bientôt disponible');

        // Gate::before : même un droit inexistant est accordé à l'Administrateur
        $this->assertTrue($admin->can('module.inexistant'));
    }

    public function test_un_role_ayant_le_droit_y_accede(): void
    {
        $gestionnaire = Utilisateur::factory()->avecDroits(['utilisateurs.gerer'])->create();

        $this->actingAs($gestionnaire)->get(route('utilisateurs.index'))->assertOk();
    }

    public function test_les_droits_suivent_le_role(): void
    {
        $vendeur = $this->avecRole(RoleSeeder::VENDEUR);
        $magasinier = $this->avecRole(RoleSeeder::MAGASINIER);

        $this->assertTrue($vendeur->can('ventes.creer'));
        $this->assertFalse($vendeur->can('stock.ajuster'));
        $this->assertTrue($magasinier->can('stock.ajuster'));
        $this->assertFalse($magasinier->can('ventes.creer'));
    }

    public function test_un_compte_desactive_pendant_la_session_est_deconnecte(): void
    {
        $admin = $this->avecRole(Role::ADMINISTRATEUR);
        $this->actingAs($admin);
        $admin->update(['actif' => false]);

        $this->get(route('utilisateurs.index'))
            ->assertRedirect(route('connexion'))
            ->assertSessionHas('erreur');

        $this->assertGuest();
    }

    public function test_la_directive_droit(): void
    {
        $gabarit = "@droit('ventes.remise') Remise autorisée @enddroit";

        $this->actingAs($this->avecRole(RoleSeeder::RESPONSABLE));
        $this->assertSame('Remise autorisée', trim(Blade::render($gabarit)));

        $this->actingAs($this->avecRole(RoleSeeder::VENDEUR));
        $this->assertSame('', trim(Blade::render($gabarit)));

        auth()->logout();
        $this->assertSame('', trim(Blade::render($gabarit)));
    }

    public function test_l_accueil_n_affiche_le_lien_utilisateurs_qu_avec_le_droit(): void
    {
        $this->actingAs($this->avecRole(RoleSeeder::VENDEUR))
            ->get(route('accueil'))
            ->assertDontSee(route('utilisateurs.index'));

        $this->actingAs($this->avecRole(Role::ADMINISTRATEUR))
            ->get(route('accueil'))
            ->assertSee(route('utilisateurs.index'));
    }

    public function test_les_capacites_hors_droits_restent_aux_policies(): void
    {
        Gate::define('produits-du-jour', fn () => true);

        $this->assertTrue($this->avecRole(RoleSeeder::VENDEUR)->can('produits-du-jour'));
    }
}
