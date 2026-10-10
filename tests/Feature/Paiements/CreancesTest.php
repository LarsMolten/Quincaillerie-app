<?php

namespace Tests\Feature\Paiements;

use App\Models\Client;
use App\Models\Facture;
use App\Models\Role;
use App\Models\Utilisateur;
use App\Models\Vente;
use Database\Seeders\ClientComptoirSeeder;
use Database\Seeders\DroitSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CreancesTest extends TestCase
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

        $this->get(route('creances.index'))
            ->assertOk()
            ->assertSeeInOrder(['Moyen Gros', 'Ancien Moyen', 'Recent Petit'])
            ->assertDontSee('Bon Payeur')
            ->assertDontSee('Vente Annulee')
            ->assertSee("410\u{00A0}000\u{00A0}Ar", false) // créances totales
            ->assertSee("90\u{00A0}000\u{00A0}Ar", false); // plus de 60 jours

        $this->assertStringNotContainsString(Client::COMPTOIR, $this->get(route('creances.index'), ['X-Fragment' => 'liste'])->getContent());
    }

    public function test_badges_d_anciennete(): void
    {
        $this->venteACredit('Recent Petit', 20000, 10);
        $this->venteACredit('Moyen Gros', 300000, 45);
        $this->venteACredit('Ancien Moyen', 90000, 90);

        $page = $this->get(route('creances.index'))->getContent();

        $this->assertMatchesRegularExpression('/Moyen Gros.*?bg-alerte-doux.*?30–60 jours/s', $page);
        $this->assertMatchesRegularExpression('/Ancien Moyen.*?bg-danger-doux.*?&gt; 60 jours/s', $page);
        $this->assertMatchesRegularExpression('/Recent Petit.*?bg-info-doux.*?&lt; 30 jours/s', $page);
        $this->assertStringContainsString('il y a 90 jour(s)', $page);
    }

    public function test_l_anciennete_suit_la_plus_vieille_vente_impayee(): void
    {
        $this->venteACredit('Client Mixte', 10000, 5);
        $this->venteACredit('Client Mixte', 10000, 70);

        $this->get(route('creances.index'))->assertSee('il y a 70 jour(s)', false);
    }

    public function test_puces_d_anciennete_et_recherche(): void
    {
        $this->venteACredit('Recent Petit', 20000, 10);
        $this->venteACredit('Ancien Moyen', 90000, 90);

        $this->get(route('creances.index', ['anciennete' => 'ancienne']))->assertSee('Ancien Moyen')->assertDontSee('Recent Petit');
        $this->get(route('creances.index', ['anciennete' => 'recente']))->assertSee('Recent Petit')->assertDontSee('Ancien Moyen');
        $this->get(route('creances.index', ['recherche' => 'ancien']))->assertSee('Ancien Moyen')->assertDontSee('Recent Petit');
    }

    public function test_aucune_creance(): void
    {
        $this->get(route('creances.index'))->assertSee('Aucune créance en cours');
    }

    public function test_detail_par_facture_du_plus_ancien_au_plus_recent(): void
    {
        $client = $this->venteACredit('Rakoto BTP', 20000, 5);
        $this->venteACredit('Rakoto BTP', 70000, 40);
        Vente::factory()->for($client)->create(); // soldée : absente du détail
        [$ancienne, $recente] = [$client->ventes()->where('reste_a_payer', 70000)->first(), $client->ventes()->where('reste_a_payer', 20000)->first()];
        Facture::factory()->create(['vente_id' => $ancienne->id, 'numero' => 'FAC-2026-00042']);

        $detail = $this->get(route('creances.show', $client))->assertOk();
        $detail->assertSeeInOrder(['FAC-2026-00042', $recente->numero])
            ->assertSee("70\u{00A0}000\u{00A0}Ar", false)
            ->assertSee('Encaisser')
            ->assertDontSee('<html', false);
        $this->assertSame(2, substr_count($detail->getContent(), 'ouvrirReglement('));
        // Adresse d'encaissement transmise à la modale (JSON échappé dans l'attribut)
        $this->assertStringContainsString(route('paiements.ventes.store', $ancienne), str_replace('\\', '', $detail->getContent()));
    }

    public function test_encaissement_depuis_la_modale_en_json(): void
    {
        $client = $this->venteACredit('Rakoto BTP', 50000, 10);
        $vente = $client->ventes()->first();
        $donnees = ['montant' => '20 000', 'mode' => 'mobile_money', 'reference' => 'MVOLA-123', 'date_paiement' => today()->toDateString()];

        $this->postJson(route('paiements.ventes.store', $vente), $donnees)
            ->assertOk()
            ->assertJsonPath('numero', 'REC-2026-00001')
            ->assertJsonPath('reste', 30000)
            ->assertJsonPath('url_recu', route('paiements.recu', 1));

        $this->assertSame(30000.0, (float) $vente->fresh()->reste_a_payer);
        $this->assertDatabaseHas('journal_activites', ['action' => 'paiement.enregistre', 'modele' => 'paiement']);

        // Dépassement du reste : refus en français sur le champ montant
        $this->postJson(route('paiements.ventes.store', $vente), [...$donnees, 'montant' => '40000'])
            ->assertStatus(422)
            ->assertJsonPath('errors.montant.0', fn (string $message) => str_contains($message, 'dépasse le reste à payer'));

        $this->postJson(route('paiements.ventes.store', $vente), [...$donnees, 'montant' => '0'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['montant' => 'Le montant doit être supérieur à 0.']);

        // Solde : la créance disparaît de la liste
        $this->postJson(route('paiements.ventes.store', $vente), [...$donnees, 'montant' => '30000'])
            ->assertOk()
            ->assertJsonPath('message', "Paiement REC-2026-00002 enregistré : {$vente->numero} est soldé.");
        $this->get(route('creances.index'))->assertSee('Aucune créance en cours');
    }

    public function test_encaissement_depuis_la_fiche_vente(): void
    {
        $vente = $this->venteACredit('Rakoto BTP', 50000, 3)->ventes()->first();

        $this->get(route('ventes.show', $vente))->assertOk()->assertSee('Encaisser')->assertSee(route('paiements.ventes.store', $vente), false);

        $this->from(route('ventes.show', $vente))
            ->post(route('paiements.ventes.store', $vente), ['montant' => '50000', 'mode' => 'especes', 'date_paiement' => today()->toDateString()])
            ->assertRedirect(route('ventes.show', $vente))
            ->assertSessionHas('toast_lien', fn (array $lien) => $lien['url'] === route('paiements.recu', 1));

        $this->get(route('ventes.show', $vente))->assertSee('REC-2026-00001')->assertDontSee(route('paiements.ventes.store', $vente), false);
    }

    public function test_ancienne_page_credits_redirigee_et_droits(): void
    {
        $this->get('/clients/credits')->assertRedirect('/creances')->assertStatus(301);

        // Le vendeur encaisse (paiements.gerer) ; le magasinier non
        $vendeur = Utilisateur::factory()->create(['role_id' => Role::where('nom', RoleSeeder::VENDEUR)->firstOrFail()->id]);
        $client = $this->venteACredit('Rakoto BTP', 50000, 3);
        $this->actingAs($vendeur)->get(route('creances.index'))->assertOk()->assertSee('Rakoto BTP');
        $this->get(route('creances.show', $client))->assertOk();

        $this->actingAs(Utilisateur::factory()->create(['role_id' => Role::where('nom', RoleSeeder::MAGASINIER)->firstOrFail()->id]));
        $this->get(route('creances.index'))->assertForbidden();
        $this->get(route('creances.show', $client))->assertForbidden();
        $this->postJson(route('paiements.ventes.store', $client->ventes()->first()), ['montant' => '1', 'mode' => 'especes', 'date_paiement' => today()->toDateString()])
            ->assertForbidden();
    }
}
