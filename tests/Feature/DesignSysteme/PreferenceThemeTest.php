<?php

namespace Tests\Feature\DesignSysteme;

use App\Enums\PreferenceTheme;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreferenceThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_l_utilisateur_connecte_enregistre_son_theme(): void
    {
        $utilisateur = Utilisateur::factory()->create();

        $this->actingAs($utilisateur)
            ->patchJson(route('preferences.theme'), ['preference_theme' => 'sombre'])
            ->assertOk()
            ->assertJson(['preference_theme' => 'sombre']);

        $this->assertSame(PreferenceTheme::Sombre, $utilisateur->fresh()->preference_theme);
    }

    public function test_une_valeur_inconnue_est_refusee_en_francais(): void
    {
        $this->actingAs(Utilisateur::factory()->create())
            ->patchJson(route('preferences.theme'), ['preference_theme' => 'violet'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['preference_theme' => 'Le thème doit être « clair », « sombre » ou « auto ».']);
    }

    public function test_un_visiteur_ne_peut_pas_enregistrer_de_theme(): void
    {
        $this->patchJson(route('preferences.theme'), ['preference_theme' => 'sombre'])
            ->assertUnauthorized();
    }
}
