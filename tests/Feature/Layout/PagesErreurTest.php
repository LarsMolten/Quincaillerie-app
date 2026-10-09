<?php

namespace Tests\Feature\Layout;

use App\Models\Utilisateur;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class PagesErreurTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_page_404_en_francais(): void
    {
        $this->actingAs(Utilisateur::factory()->create())
            ->get('/page-qui-n-existe-pas')
            ->assertNotFound()
            ->assertSee('Page introuvable')
            ->assertSee('ERREUR 404')
            ->assertSee('Retour')
            ->assertSee('lang="fr"', false);
    }

    public function test_page_419_session_expiree(): void
    {
        Route::middleware('web')->post('/essai-419', fn () => 'ok');

        // Vérification CSRF réelle (désactivée par défaut pendant les tests)
        $this->app->instance(PreventRequestForgery::class, new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });

        $this->post('/essai-419')
            ->assertStatus(419)
            ->assertSee('Session expirée')
            ->assertSee('Recharger la page');
    }

    public function test_page_500_sans_detail_technique(): void
    {
        config(['app.debug' => false]);
        Route::middleware('web')->get('/essai-500', fn () => throw new RuntimeException('Détail interne confidentiel'));

        $this->get('/essai-500')
            ->assertStatus(500)
            ->assertSee('Erreur interne')
            ->assertSee('Aucune donnée n&#039;a été perdue', false)
            ->assertDontSee('Détail interne confidentiel');
    }

    public function test_page_403_avec_le_message_du_droit(): void
    {
        Route::middleware('web')->get('/essai-403', fn () => abort(403, 'Vous n\'avez pas le droit de voir les finances.'));

        $this->get('/essai-403')
            ->assertForbidden()
            ->assertSee('Accès refusé')
            ->assertSee('Vous n&#039;avez pas le droit de voir les finances.', false);
    }
}
