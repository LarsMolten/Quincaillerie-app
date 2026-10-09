<?php

namespace Tests\Feature\Clients;

use App\Models\Client;
use App\Models\Role;
use App\Models\Utilisateur;
use App\Models\Vente;
use Database\Seeders\ClientComptoirSeeder;
use Database\Seeders\DroitSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CreditsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-09 10:00:00');
        $this->withoutVite();
        $this->seed([RoleSeeder::class, DroitSeeder::class, ClientComptoirSeeder::class]);
        $this->actingAs(Utilisateur::factory()->create([
            'role_id' => Role::where('nom', RoleSeeder::RESPONSABLE)->firstOrFail()->id,
        ]));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function venteACredit(string $nom, int $reste, int $joursAvant): Client
    {
        $client = Client::firstOrCreate(['nom' => $nom], ['plafond_credit' => 2000000, 'actif' => true]);
        Vente::factory()->for($client)->aCredit($reste)->create(['date_vente' => now()->subDays($joursAvant)]);

        return $client;
    }

    public function test_seulement_les_clients_debiteurs_tries_par_montant(): void
    {
        $this->venteACredit('Recent Petit', 20000, 10);
        $this->venteACredit('Moyen Gros', 300000, 45);
        $this->venteACredit('Ancien Moyen', 90000, 90);

        Vente::factory()->for(Client::factory()->create(['nom' => 'Bon Payeur']))->create(); // soldée
        Vente::factory()->for(Client::factory()->create(['nom' => 'Vente Annulee']))->aCredit(50000)->annulee()->create();
        $comptoir = Client::where('nom', Client::COMPTOIR)->first();
        Vente::factory()->for($comptoir)->aCredit(1000)->create(); // anomalie : le comptoir n'apparaît jamais

        $this->get(route('clients.credits'))
            ->assertOk()
            ->assertSeeInOrder(['Moyen Gros', 'Ancien Moyen', 'Recent Petit'])
            ->assertDontSee('Bon Payeur')
            ->assertDontSee('Vente Annulee')
            ->assertSee("410\u{00A0}000\u{00A0}Ar", false) // créances totales
            ->assertSee("90\u{00A0}000\u{00A0}Ar", false); // plus de 60 jours

        $this->assertStringNotContainsString(Client::COMPTOIR, $this->get(route('clients.credits'), ['X-Fragment' => 'liste'])->getContent());
    }

    public function test_badges_d_anciennete(): void
    {
        $this->venteACredit('Recent Petit', 20000, 10);
        $this->venteACredit('Moyen Gros', 300000, 45);
        $this->venteACredit('Ancien Moyen', 90000, 90);

        $page = $this->get(route('clients.credits'))->getContent();

        $this->assertMatchesRegularExpression('/Moyen Gros.*?bg-alerte-doux.*?30–60 jours/s', $page);
        $this->assertMatchesRegularExpression('/Ancien Moyen.*?bg-danger-doux.*?&gt; 60 jours/s', $page);
        $this->assertMatchesRegularExpression('/Recent Petit.*?bg-info-doux.*?&lt; 30 jours/s', $page);
        $this->assertStringContainsString('il y a 90 jour(s)', $page);
    }

    public function test_l_anciennete_suit_la_plus_vieille_vente_impayee(): void
    {
        $this->venteACredit('Client Mixte', 10000, 5);
        $this->venteACredit('Client Mixte', 10000, 70);

        $this->get(route('clients.credits'))->assertSee('il y a 70 jour(s)', false);
    }

    public function test_puces_d_anciennete_et_recherche(): void
    {
        $this->venteACredit('Recent Petit', 20000, 10);
        $this->venteACredit('Ancien Moyen', 90000, 90);

        $this->get(route('clients.credits', ['anciennete' => 'ancienne']))->assertSee('Ancien Moyen')->assertDontSee('Recent Petit');
        $this->get(route('clients.credits', ['anciennete' => 'recente']))->assertSee('Recent Petit')->assertDontSee('Ancien Moyen');
        $this->get(route('clients.credits', ['recherche' => 'ancien']))->assertSee('Ancien Moyen')->assertDontSee('Recent Petit');
    }

    public function test_aucun_credit(): void
    {
        $this->get(route('clients.credits'))->assertSee('Aucun crédit en cours');
    }
}
