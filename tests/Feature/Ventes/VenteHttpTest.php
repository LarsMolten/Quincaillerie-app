<?php

namespace Tests\Feature\Ventes;

use App\Enums\ModePaiement;
use App\Enums\StatutVente;
use App\Enums\TypeMouvementStock;
use App\Models\Categorie;
use App\Models\Client;
use App\Models\Droit;
use App\Models\Produit;
use App\Models\Role;
use App\Models\Utilisateur;
use App\Models\Vente;
use App\Services\MouvementStockService;
use App\Services\VenteService;
use Database\Seeders\ClientComptoirSeeder;
use Database\Seeders\DroitSeeder;
use Database\Seeders\ParametreSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VenteHttpTest extends TestCase
{
    use RefreshDatabase;

    private Client $comptoir;

    private Produit $ciment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed([RoleSeeder::class, DroitSeeder::class, ParametreSeeder::class, ClientComptoirSeeder::class]);
        $this->actingAs($this->avecRole(RoleSeeder::RESPONSABLE));
        $this->comptoir = Client::where('nom', Client::COMPTOIR)->firstOrFail();
        $this->ciment = Produit::factory()->create(['nom' => 'Ciment Holcim', 'code_barres' => '2000000000015', 'stock_actuel' => 0, 'prix_vente' => 38000, 'prix_gros' => 36000, 'prix_achat' => 33000]);
        app(MouvementStockService::class)->enregistrer($this->ciment, TypeMouvementStock::Achat, 50);
    }

    private function avecRole(string $role): Utilisateur
    {
        return Utilisateur::factory()->create(['role_id' => Role::where('nom', $role)->firstOrFail()->id]);
    }

    private function donnees(array $surcharges = []): array
    {
        return [
            'client_id' => $this->comptoir->id,
            'lignes' => [['produit_id' => $this->ciment->id, 'quantite' => 2, 'tarif' => 'detail', 'remise' => 0]],
            'remise' => 0,
            'mode_paiement' => 'especes',
            'montant_recu' => 100000,
            ...$surcharges,
        ];
    }

    private function vente(): Vente
    {
        return app(VenteService::class)->creer($this->comptoir, [['produit_id' => $this->ciment->id, 'quantite' => 1]], 0, ModePaiement::Especes, 38000);
    }

    public function test_ecran_de_caisse(): void
    {
        $this->get(route('ventes.create'))
            ->assertOk()
            ->assertSee('Caisse')
            ->assertSee('Client comptoir')
            ->assertSee('Raccourcis clavier')
            ->assertSee('Valider la vente')
            ->assertSee('caisse-ticket-'.auth()->id());
    }

    public function test_catalogue_json_actifs_categorie_et_code_barres(): void
    {
        $autreCategorie = Categorie::factory()->create();
        Produit::factory()->inactif()->create(['nom' => 'Ciment ancien']);
        Produit::factory()->create(['nom' => 'Tuyau PVC', 'categorie_id' => $autreCategorie->id]);

        $this->getJson(route('ventes.catalogue', ['q' => 'ciment']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.nom', 'Ciment Holcim')
            ->assertJsonPath('0.prix_vente', 38000)
            ->assertJsonPath('0.prix_gros', 36000)
            ->assertJsonPath('0.stock', 50)
            ->assertJsonPath('0.etat', 'normal');

        $this->getJson(route('ventes.catalogue', ['q' => '2000000000015']))->assertJsonPath('0.produit_id', $this->ciment->id);
        $this->getJson(route('ventes.catalogue', ['categorie' => $autreCategorie->id]))->assertJsonCount(1)->assertJsonPath('0.nom', 'Tuyau PVC');
        $this->getJson(route('ventes.catalogue'))->assertJsonCount(2);
    }

    public function test_clients_json_comptoir_en_premier_et_credit_disponible(): void
    {
        Client::factory()->create(['nom' => 'Andry Construction', 'plafond_credit' => 200000]);

        $this->getJson(route('ventes.clients'))
            ->assertOk()
            ->assertJsonPath('0.nom', 'Client comptoir')
            ->assertJsonPath('0.comptoir', true)
            ->assertJsonPath('0.plafond', null);

        $this->getJson(route('ventes.clients', ['q' => 'andry']))
            ->assertJsonCount(1)
            ->assertJsonPath('0.disponible', 200000);
    }

    public function test_enregistrement_json(): void
    {
        $reponse = $this->postJson(route('ventes.store'), $this->donnees())->assertCreated();

        $vente = Vente::firstOrFail();
        $reponse->assertJsonPath('numero', $vente->numero)
            ->assertJsonPath('total', 76000)
            ->assertJsonPath('monnaie', 24000)
            ->assertJsonPath('facture', $vente->facture->numero)
            ->assertJsonPath('url_ticket', route('ventes.ticket', $vente));

        $this->assertEquals(48, $this->ciment->fresh()->stock_actuel);
    }

    public function test_refus_metier_en_422_avec_message_francais(): void
    {
        $this->postJson(route('ventes.store'), $this->donnees(['lignes' => [['produit_id' => $this->ciment->id, 'quantite' => 60, 'tarif' => 'detail']]]))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Stock insuffisant pour « Ciment Holcim » : 50 disponible(s), 60 demandé(s).');

        $this->postJson(route('ventes.store'), $this->donnees(['mode_paiement' => 'credit']))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Le crédit est interdit pour le Client comptoir : encaissez la totalité ou choisissez un client.');

        $this->postJson(route('ventes.store'), $this->donnees(['lignes' => []]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lignes' => 'Le ticket est vide : ajoutez au moins un produit.']);

        $this->postJson(route('ventes.store'), $this->donnees(['lignes' => [['produit_id' => $this->ciment->id, 'quantite' => 0, 'tarif' => 'detail']]]))
            ->assertJsonValidationErrors(['lignes.0.quantite' => 'La ligne 1 : quantité doit être supérieure à 0.']);

        $this->assertSame(0, Vente::count());
    }

    public function test_remise_refusee_au_vendeur_par_la_route(): void
    {
        $this->actingAs($this->avecRole(RoleSeeder::VENDEUR));

        $this->get(route('ventes.create'))->assertOk()->assertDontSee('caisse-remise-valeur');

        $this->postJson(route('ventes.store'), $this->donnees(['remise' => 1000]))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Vous n\'avez pas le droit d\'accorder une remise.');
    }

    public function test_liste_filtres_et_fragment(): void
    {
        $aujourdhui = $this->vente();
        $ancienne = $this->vente();
        $ancienne->update(['date_vente' => now()->subDays(40)]);
        $annulee = $this->vente();
        app(VenteService::class)->annuler($annulee, 'Erreur de caisse');

        $this->get(route('ventes.index'))
            ->assertOk()
            ->assertSee($aujourdhui->numero)
            ->assertDontSee($ancienne->numero)
            ->assertSee('Annulée');

        $this->get(route('ventes.index', ['periode' => 'tout']))->assertSee($ancienne->numero);
        $this->get(route('ventes.index', ['statut' => 'annulees']))->assertSee($annulee->numero)->assertDontSee($aujourdhui->numero);
        $this->get(route('ventes.index', ['vendeur' => 999]))->assertSee('Aucune vente trouvée');

        $fragment = $this->get(route('ventes.index'), ['X-Fragment' => 'liste'])->getContent();
        $this->assertStringNotContainsString('<!DOCTYPE', $fragment);
    }

    public function test_fiche_et_ticket_pdf(): void
    {
        $vente = $this->vente();

        $this->get(route('ventes.show', $vente))
            ->assertOk()
            ->assertSee($vente->numero)
            ->assertSee($vente->facture->numero)
            ->assertSee('Ciment Holcim')
            ->assertSee('Annuler la vente');

        $pdf = $this->get(route('ventes.ticket', $vente))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_annulation_par_la_route_avec_motif_obligatoire(): void
    {
        $vente = $this->vente();

        $this->post(route('ventes.annuler', $vente), ['motif' => ''])->assertSessionHasErrors('motif');
        $this->assertSame(StatutVente::Validee, $vente->fresh()->statut);

        $this->post(route('ventes.annuler', $vente), ['motif' => 'Client a renoncé'])
            ->assertSessionHas('succes', "Vente {$vente->numero} annulée : stock remis et facture annulée.");
        $this->assertSame(StatutVente::Annulee, $vente->fresh()->statut);
        $this->assertEquals(50, $this->ciment->fresh()->stock_actuel);

        $this->get(route('ventes.show', $vente))->assertSee('Vente annulée')->assertSee('Client a renoncé');
    }

    public function test_droits(): void
    {
        $vente = $this->vente();

        $this->actingAs($this->avecRole(RoleSeeder::MAGASINIER));
        $this->get(route('ventes.index'))->assertForbidden();
        $this->get(route('ventes.create'))->assertForbidden();
        $this->postJson(route('ventes.store'), $this->donnees())->assertForbidden();
        $this->getJson(route('ventes.catalogue'))->assertForbidden();

        // Le vendeur vend mais n'annule pas
        $this->actingAs($this->avecRole(RoleSeeder::VENDEUR));
        $this->get(route('ventes.show', $vente))->assertOk()->assertDontSee('Annuler la vente');
        $this->post(route('ventes.annuler', $vente), ['motif' => 'Tentative'])->assertForbidden();

        // Consultation seule
        $lecteur = Utilisateur::factory()->create();
        $lecteur->role->droits()->attach(Droit::where('code', 'ventes.voir')->value('id'));
        $this->actingAs($lecteur);
        $this->get(route('ventes.index'))->assertOk()->assertDontSee('Nouvelle vente');
        $this->get(route('ventes.create'))->assertForbidden();
    }
}
