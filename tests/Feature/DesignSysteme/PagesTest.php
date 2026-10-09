<?php

namespace Tests\Feature\DesignSysteme;

use App\Enums\PreferenceTheme;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_la_page_design_systeme_montre_tous_les_composants_dans_les_deux_modes(): void
    {
        $this->get(route('design-systeme'))
            ->assertOk()
            ->assertSee('class="clair', false)
            ->assertSee('class="dark', false)
            ->assertSeeInOrder(['Jetons de couleur', 'Typographie', 'Boutons', 'Champs de formulaire', 'Cartes et statistiques',
                'Badges de statut', 'Tableau', 'Modales et notifications', 'Chargement et état vide', 'Icônes Lucide']);
    }

    public function test_la_page_design_systeme_est_introuvable_en_production(): void
    {
        $this->app['env'] = 'production';

        $this->get(route('design-systeme'))->assertNotFound();
    }

    public function test_le_script_de_theme_est_dans_le_head_avant_les_styles(): void
    {
        $page = $this->get(route('connexion'))->assertOk()->getContent();

        $this->assertLessThan(strpos($page, '</head>'), strpos($page, "document.documentElement.classList.toggle('dark'"));
        $this->assertStringContainsString('var mode = null;', $page);
    }

    public function test_le_script_de_theme_recoit_la_preference_de_l_utilisateur_connecte(): void
    {
        $utilisateur = Utilisateur::factory()->create(['preference_theme' => PreferenceTheme::Sombre]);

        $this->actingAs($utilisateur)
            ->get(route('accueil'))
            ->assertSee('var mode = "sombre";', false)
            ->assertSee('name="preference-theme-url"', false);
    }

    public function test_les_messages_flash_deviennent_des_toasts(): void
    {
        $this->actingAs(Utilisateur::factory()->create())
            ->withSession(['succes' => 'Produit enregistré.', 'erreur' => 'Stock insuffisant.'])
            ->get(route('accueil'))
            ->assertSee('<script type="application/json" id="toasts-flash">', false)
            ->assertSee('{"type":"succes","message":"Produit enregistré."}', false)
            ->assertSee('"type":"erreur"', false)
            ->assertSee('aria-live="polite"', false);
    }
}
