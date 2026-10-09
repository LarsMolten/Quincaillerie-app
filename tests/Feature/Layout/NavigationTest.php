<?php

namespace Tests\Feature\Layout;

use App\Models\Role;
use App\Models\Utilisateur;
use App\Support\Navigation;
use Database\Seeders\DroitSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed([RoleSeeder::class, DroitSeeder::class]);
    }

    private function avecRole(string $role): Utilisateur
    {
        return Utilisateur::factory()->create([
            'nom' => 'Rakotonirina Andry',
            'role_id' => Role::where('nom', $role)->firstOrFail()->id,
        ]);
    }

    /** Libellés des entrées visibles dans la navigation principale (barre latérale). */
    private function libellesMenu(Utilisateur $utilisateur): array
    {
        $this->actingAs($utilisateur);

        return collect(Navigation::pour($utilisateur))->pluck('entrees')->flatten(1)->pluck('libelle')->all();
    }

    public function test_l_administrateur_voit_tout_le_menu_du_cahier_des_charges(): void
    {
        $page = $this->actingAs($this->avecRole(Role::ADMINISTRATEUR))->get(route('accueil'))->assertOk();

        foreach (['Stock', 'Ventes', 'Achats', 'Clients', 'Finances', 'Rapports', 'Administration'] as $section) {
            $page->assertSee($section);
        }

        foreach (Navigation::sections() as $section) {
            foreach ($section['entrees'] as $entree) {
                $page->assertSee('href="'.route($entree['route']).'"', false);
            }
        }

        $page->assertSee("Journal d'activité")->assertSee('Rôles et droits');
    }

    public function test_un_vendeur_ne_voit_que_les_entrees_autorisees(): void
    {
        $libelles = $this->libellesMenu($this->avecRole(RoleSeeder::VENDEUR));

        $this->assertEqualsCanonicalizing(
            ['Tableau de bord', 'Produits', 'Nouvelle vente', 'Liste des ventes', 'Factures', 'Clients', 'Crédits', 'Historique', 'Paiements'],
            $libelles,
        );

        $this->get(route('accueil'))
            ->assertDontSee(route('utilisateurs.index'))
            ->assertDontSee(route('achats.index'))
            ->assertDontSee(route('depenses.index'))
            ->assertDontSee('Administration');
    }

    public function test_le_magasinier_voit_le_stock(): void
    {
        $this->assertEqualsCanonicalizing(
            ['Tableau de bord', 'Produits', 'Entrées', 'Sorties', 'Mouvements', 'Inventaire'],
            $this->libellesMenu($this->avecRole(RoleSeeder::MAGASINIER)),
        );
    }

    public function test_l_entree_active_est_signalee(): void
    {
        $page = $this->actingAs($this->avecRole(Role::ADMINISTRATEUR))
            ->get(route('ventes.create'))
            ->assertOk()
            ->getContent();

        // Liens portant aria-current : barre latérale, tiroir mobile et barre inférieure
        preg_match_all('/<a\s+href="([^"]+)"[^>]*?aria-current="page"/s', $page, $actifs);

        $this->assertCount(3, $actifs[1]);
        // « Liste des ventes » (/ventes) n'est pas active sur la page « Nouvelle vente »
        $this->assertSame([route('ventes.create')], array_values(array_unique($actifs[1])));
    }

    public function test_chaque_module_a_venir_est_protege_par_son_droit(): void
    {
        $admin = $this->avecRole(Role::ADMINISTRATEUR);
        $sansDroit = Utilisateur::factory()->create(); // rôle sans aucun droit

        foreach (Navigation::aVenir() as $entree) {
            $this->actingAs($admin)
                ->get($entree['url'])
                ->assertOk()
                ->assertSee('Bientôt disponible')
                ->assertSee('<h1', false);

            $this->actingAs($sansDroit)->get($entree['url'])->assertForbidden();
        }
    }

    public function test_en_tete_et_barre_mobile(): void
    {
        $vendeur = $this->avecRole(RoleSeeder::VENDEUR);

        $page = $this->actingAs($vendeur)
            ->get(route('accueil'))
            ->assertSee('Aller au contenu')
            ->assertSee('id="contenu"', false)
            ->assertSee('aria-label="Navigation principale"', false)
            ->assertSee('Ctrl K')
            ->assertSee('aria-label="Notifications"', false)
            ->assertSee('x-data="theme"', false)
            ->assertSee('Vendeur/Caissier')
            ->assertSee('action="'.route('deconnexion').'"', false)
            ->assertSee('aria-label="Navigation rapide"', false)
            ->assertSeeInOrder(['Navigation rapide', 'Accueil', 'Vente', 'Produits', 'Menu'])
            ->assertSee('id="tiroir-navigation"', false)
            ->getContent();

        // Avatar à initiales
        $this->assertMatchesRegularExpression('/aria-hidden="true">\s*RA\s*<\/span>/', $page);
    }

    public function test_initiales_et_prenom(): void
    {
        $utilisateur = new Utilisateur(['nom' => 'Rasoanirina Voahangy']);

        $this->assertSame('RV', $utilisateur->initiales);
        $this->assertSame('Voahangy', $utilisateur->prenom);
        $this->assertSame('A', (new Utilisateur(['nom' => 'Administrateur']))->initiales);
    }
}
