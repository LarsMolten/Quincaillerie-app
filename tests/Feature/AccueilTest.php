<?php

namespace Tests\Feature;

use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccueilTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_page_d_accueil_s_affiche_en_francais(): void
    {
        $this->withoutVite();

        $this->actingAs(Utilisateur::factory()->create(['nom' => 'Rakoto Hery']))
            ->get(route('accueil'))
            ->assertOk()
            ->assertSee('Bonjour, Rakoto Hery')
            ->assertSee('lang="fr"', false)
            ->assertSee("35\u{00A0}000\u{00A0}Ar");
    }

    public function test_la_configuration_regionale_est_appliquee(): void
    {
        $this->assertSame('fr', app()->getLocale());
        $this->assertSame('Indian/Antananarivo', config('app.timezone'));
        $this->assertSame('Le champ email est obligatoire.', __('validation.required', ['attribute' => 'email']));
    }
}
