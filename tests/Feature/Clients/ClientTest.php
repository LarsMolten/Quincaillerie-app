<?php

namespace Tests\Feature\Clients;

use App\Models\Client;
use App\Models\Facture;
use App\Models\Paiement;
use App\Models\Role;
use App\Models\Utilisateur;
use App\Models\Vente;
use Database\Seeders\ClientComptoirSeeder;
use Database\Seeders\DroitSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientTest extends TestCase
{
    use RefreshDatabase;

    private Client $comptoir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed([RoleSeeder::class, DroitSeeder::class, ClientComptoirSeeder::class]);
        $this->actingAs($this->avecRole(RoleSeeder::VENDEUR));
        $this->comptoir = Client::where('nom', Client::COMPTOIR)->firstOrFail();
    }

    private function avecRole(string $role): Utilisateur
    {
        return Utilisateur::factory()->create(['role_id' => Role::where('nom', $role)->firstOrFail()->id]);
    }

    public function test_liste_comptoir_en_tete_et_recherche(): void
    {
        Client::factory()->create(['nom' => 'Andriamasy Fara', 'telephone' => '034 11 222 33']);
        Client::factory()->create(['nom' => 'Rabe Tiana']);

        $this->get(route('clients.index'))
            ->assertOk()
            ->assertSeeInOrder([Client::COMPTOIR, 'Andriamasy Fara', 'Rabe Tiana'])
            ->assertSee('Par défaut')
            ->assertSee('>AF<', false);

        $this->get(route('clients.index', ['recherche' => '222 33']))->assertSee('Andriamasy Fara')->assertDontSee('Rabe Tiana');
    }

    public function test_tri_par_creance(): void
    {
        Vente::factory()->for(Client::factory()->create(['nom' => 'Petit débiteur']))->aCredit(5000)->create();
        Vente::factory()->for(Client::factory()->create(['nom' => 'Gros débiteur']))->aCredit(400000)->create();

        $this->get(route('clients.index', ['tri' => 'creance', 'ordre' => 'desc']))->assertSeeInOrder(['Gros débiteur', 'Petit débiteur']);
    }

    public function test_creation_modification_et_validation(): void
    {
        $this->post(route('clients.store'), ['nom' => 'Rakoto Hery', 'plafond_credit' => '500 000'])
            ->assertSessionHas('succes', 'Client « Rakoto Hery » créé.');
        $client = Client::where('nom', 'Rakoto Hery')->firstOrFail();
        $this->assertEquals(500000, $client->plafond_credit);

        $this->put(route('clients.update', $client), ['nom' => 'Rakoto Hery', 'plafond_credit' => ''])->assertSessionHas('succes');
        $this->assertNull($client->fresh()->plafond_credit);

        $this->post(route('clients.store'), ['nom' => '', 'plafond_credit' => '-5'])
            ->assertSessionHasErrors(['nom' => 'Le champ nom est obligatoire.', 'plafond_credit']);
    }

    public function test_le_nom_client_comptoir_est_reserve(): void
    {
        $this->post(route('clients.store'), ['nom' => 'client COMPTOIR'])
            ->assertSessionHasErrors(['nom' => 'Le nom « Client comptoir » est réservé au client par défaut.']);
    }

    public function test_le_client_comptoir_est_protege(): void
    {
        $this->delete(route('clients.destroy', $this->comptoir))->assertSessionHas('erreur');
        $this->patch(route('clients.statut', $this->comptoir))->assertSessionHas('erreur');
        $this->put(route('clients.update', $this->comptoir), ['nom' => 'Autre nom'])->assertSessionHas('erreur');
        $this->put(route('clients.update', $this->comptoir), ['nom' => Client::COMPTOIR, 'plafond_credit' => '100000'])->assertSessionHas('erreur');

        $comptoir = $this->comptoir->fresh();
        $this->assertNotSoftDeleted($comptoir);
        $this->assertTrue($comptoir->actif);
        $this->assertSame(Client::COMPTOIR, $comptoir->nom);
        $this->assertNull($comptoir->plafond_credit);

        // Ses coordonnées restent modifiables
        $this->put(route('clients.update', $this->comptoir), ['nom' => Client::COMPTOIR, 'telephone' => '020 22 000 00'])->assertSessionHas('succes');
        $this->assertSame('020 22 000 00', $this->comptoir->fresh()->telephone);

        // Pas d'action de suppression ni de désactivation proposée
        $this->get(route('clients.show', $this->comptoir))
            ->assertSee('Client comptoir : crédit jamais autorisé.')
            ->assertDontSee('supprimer-client-'.$this->comptoir->id, false)
            ->assertDontSee('statut-client-'.$this->comptoir->id, false);
    }

    public function test_fiche_plafond_creance_ventes_factures_et_paiements(): void
    {
        $client = Client::factory()->create(['nom' => 'Rasoa Lova', 'plafond_credit' => 200000]);
        $vente = Vente::factory()->for($client)->aCredit(150000)->create(['numero' => 'VTE-2026-00077']);
        Facture::factory()->for($vente)->create(['numero' => 'FAC-2026-00077']);
        Paiement::factory()->create(['payable_type' => 'vente', 'payable_id' => $vente->id, 'montant' => 25000]);

        $this->get(route('clients.show', $client))
            ->assertOk()
            ->assertSee('role="progressbar"', false)
            ->assertSee('aria-valuenow="75"', false)
            ->assertSee("150\u{00A0}000\u{00A0}Ar", false)
            ->assertSee('VTE-2026-00077')
            ->assertSee('Facture FAC-2026-00077')
            ->assertSee('Non soldée')
            ->assertSee("25\u{00A0}000\u{00A0}Ar", false);
    }

    public function test_sans_plafond_credit_non_autorise_et_plafond_depasse(): void
    {
        $this->get(route('clients.show', Client::factory()->create(['plafond_credit' => null])))
            ->assertSee('Crédit non autorisé : aucun plafond défini.');

        $depasse = Client::factory()->create(['plafond_credit' => 100000]);
        Vente::factory()->for($depasse)->aCredit(120000)->create();
        $this->get(route('clients.show', $depasse))
            ->assertSee('aria-valuenow="100"', false)
            ->assertSee('Plafond atteint');
    }

    public function test_suppression_refusee_avec_ventes_et_douce_sinon(): void
    {
        $avecVente = Client::factory()->create(['nom' => 'Fidèle']);
        Vente::factory()->for($avecVente)->create();

        $this->delete(route('clients.destroy', $avecVente))
            ->assertSessionHas('erreur', 'Le client « Fidèle » a des ventes enregistrées : il ne peut pas être supprimé. Désactivez-le plutôt.');

        $sansVente = Client::factory()->create();
        $this->delete(route('clients.destroy', $sansVente));
        $this->assertSoftDeleted($sansVente);
        $this->assertDatabaseHas('journal_activites', ['action' => 'client.supprime', 'modele' => 'client', 'modele_id' => $sansVente->id]);
    }

    public function test_desactivation_journalisee(): void
    {
        $client = Client::factory()->create();

        $this->patch(route('clients.statut', $client));
        $this->assertFalse($client->fresh()->actif);
        $this->assertDatabaseHas('journal_activites', ['action' => 'client.desactive', 'modele_id' => $client->id]);
    }

    public function test_droits(): void
    {
        // Le vendeur gère les clients (droit clients.gerer) ; le magasinier non
        $this->get(route('clients.index'))->assertOk();
        $this->get(route('clients.credits'))->assertOk();

        $this->actingAs($this->avecRole(RoleSeeder::MAGASINIER));
        $this->get(route('clients.index'))->assertForbidden();
        $this->get(route('clients.credits'))->assertForbidden();
        $this->get(route('clients.show', $this->comptoir))->assertForbidden();
        $this->post(route('clients.store'), ['nom' => 'x'])->assertForbidden();
    }

    public function test_historique_reste_a_venir(): void
    {
        $this->get('/clients/historique')->assertOk()->assertSee('Bientôt disponible');
    }
}
