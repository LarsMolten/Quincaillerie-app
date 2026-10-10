<?php

namespace Tests\Feature\Retours;

use App\Enums\ModePaiement;
use App\Enums\StatutRetour;
use App\Enums\TypeMouvementStock;
use App\Models\Achat;
use App\Models\Client;
use App\Models\Fournisseur;
use App\Models\Produit;
use App\Models\Retour;
use App\Models\Role;
use App\Models\Utilisateur;
use App\Models\Vente;
use App\Services\AchatService;
use App\Services\MouvementStockService;
use App\Services\RetourService;
use App\Services\VenteService;
use App\Support\Entreprise;
use Database\Seeders\ClientComptoirSeeder;
use Database\Seeders\DroitSeeder;
use Database\Seeders\ParametreSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RetourHttpTest extends TestCase
{
    use RefreshDatabase;

    private Produit $ciment;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-11 09:00:00');
        $this->withoutVite();
        $this->seed([RoleSeeder::class, DroitSeeder::class, ParametreSeeder::class, ClientComptoirSeeder::class]);
        $this->actingAs($this->avecRole(RoleSeeder::RESPONSABLE));
        $this->client = Client::factory()->create(['nom' => 'Rakoto BTP', 'plafond_credit' => 1000000]);
        $this->ciment = Produit::factory()->create(['nom' => 'Ciment 50 kg', 'stock_actuel' => 0, 'prix_achat' => 33000, 'prix_vente' => 40000]);
        app(MouvementStockService::class)->enregistrer($this->ciment, TypeMouvementStock::Achat, 100);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function avecRole(string $role): Utilisateur
    {
        return Utilisateur::factory()->create(['role_id' => Role::where('nom', $role)->firstOrFail()->id]);
    }

    private function vente(ModePaiement $mode = ModePaiement::Credit): Vente
    {
        return app(VenteService::class)->creer($this->client, [['produit_id' => $this->ciment->id, 'quantite' => 5]], 0, $mode, $mode === ModePaiement::Credit ? 0 : 200000);
    }

    private function achat(): Achat
    {
        return app(AchatService::class)->creer(Fournisseur::factory()->create(['nom' => 'Holcim Madagascar', 'actif' => true]),
            [['produit_id' => $this->ciment->id, 'quantite' => 10, 'prix_achat' => 33000]], 0, null);
    }

    public function test_recherche_des_documents_et_detail_retournable(): void
    {
        $vente = $this->vente();
        $annulee = $this->vente(ModePaiement::Especes);
        app(VenteService::class)->annuler($annulee, 'Erreur de caisse');

        $this->getJson(route('retours.documents', ['type' => 'client', 'q' => 'rakoto']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.numero', $vente->numero)
            ->assertJsonPath('0.facture', $vente->facture->numero)
            ->assertJsonPath('0.reste', 200000);

        $this->getJson(route('retours.documents', ['type' => 'client', 'q' => $vente->facture->numero]))->assertJsonCount(1);

        app(RetourService::class)->creerClient($vente, [$this->ciment->id => 2], 'Produit défectueux');
        $this->getJson(route('retours.documents', ['type' => 'client', 'id' => $vente->id]))
            ->assertOk()
            ->assertJsonPath('lignes.0.nom', 'Ciment 50 kg')
            ->assertJsonPath('lignes.0.quantite', 5)
            ->assertJsonPath('lignes.0.retourne', 2)
            ->assertJsonPath('lignes.0.retournable', 3)
            ->assertJsonPath('lignes.0.prix_unitaire', 40000);

        $achat = $this->achat();
        $this->getJson(route('retours.documents', ['type' => 'fournisseur', 'q' => 'holcim']))->assertJsonPath('0.numero', $achat->numero);
    }

    public function test_creation_par_l_assistant_201_et_refus_422(): void
    {
        $vente = $this->vente();
        $donnees = ['type' => 'client', 'vente_id' => $vente->id, 'lignes' => [['produit_id' => $this->ciment->id, 'quantite' => 2]], 'motif' => 'Produit défectueux', 'precision' => 'sacs déchirés'];

        $this->postJson(route('retours.store'), [...$donnees, 'motif' => 'Autre', 'precision' => ''])
            ->assertStatus(422)->assertJsonValidationErrors(['precision' => 'Précisez le motif du retour.']);
        $this->postJson(route('retours.store'), [...$donnees, 'motif' => 'Inventé'])
            ->assertStatus(422)->assertJsonValidationErrors(['motif' => 'Choisissez un motif dans la liste.']);
        $this->postJson(route('retours.store'), [...$donnees, 'lignes' => []])
            ->assertStatus(422)->assertJsonValidationErrors(['lignes' => 'Indiquez au moins un produit à retourner.']);
        $this->postJson(route('retours.store'), [...$donnees, 'lignes' => [['produit_id' => $this->ciment->id, 'quantite' => 9]]])
            ->assertStatus(422)->assertJsonPath('errors.lignes.0', 'Retour impossible pour « Ciment 50 kg » : 5 retournable(s) sur 5, 9 demandé(s).');

        $reponse = $this->postJson(route('retours.store'), $donnees)
            ->assertCreated()
            ->assertJsonPath('numero', 'RET-2026-00001')
            ->assertJsonPath('avoir', 80000)
            ->assertJsonPath('rembourse', 0);

        $retour = Retour::sole();
        $this->assertSame('Produit défectueux — sacs déchirés', $retour->motif);
        $this->assertSame(route('retours.show', $retour), $reponse->json('url'));
        $this->assertEquals(97, $this->ciment->fresh()->stock_actuel);
    }

    public function test_listes_detail_et_bon_pdf(): void
    {
        $vente = $this->vente();
        $retour = app(RetourService::class)->creerClient($vente, [$this->ciment->id => 1], 'Erreur de produit');
        $achat = $this->achat();
        $retourFournisseur = app(RetourService::class)->creerFournisseur($achat, [$this->ciment->id => 2], 'Livraison en excès');

        $this->get(route('retours.clients'))->assertOk()->assertSee($retour->numero)->assertSee('Rakoto BTP')->assertDontSee($retourFournisseur->numero);
        $this->get(route('retours.fournisseurs'))->assertOk()->assertSee($retourFournisseur->numero)->assertSee('Holcim Madagascar')->assertDontSee($retour->numero);
        $this->get(route('retours.clients', ['recherche' => $vente->facture->numero]))->assertSee($retour->numero);
        $this->get(route('retours.clients', ['statut' => 'annules']))->assertDontSee($retour->numero);
        $fragment = $this->get(route('retours.clients'), ['X-Fragment' => 'liste'])->getContent();
        $this->assertStringNotContainsString('<!DOCTYPE', $fragment);

        $this->get(route('retours.show', $retour))->assertOk()
            ->assertSee('Erreur de produit')->assertSee($vente->facture->numero)->assertSee('Annuler le retour');
        $this->get(route('ventes.show', $vente))->assertSee(route('retours.create', ['vente' => $vente->id]), false)->assertSee($retour->numero);

        $pdf = $this->get(route('retours.bon', $retour))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $json = $this->getJson(route('retours.bon', $retourFournisseur))->assertOk()->assertJsonPath('nom', "retour-{$retourFournisseur->numero}.pdf");
        $this->assertStringStartsWith('%PDF', base64_decode($json->json('pdf')));

        $html = view('retours.bon', [
            'retour' => $retour->fresh(), 'document' => $vente, 'tiers' => 'Rakoto BTP', 'codeBarres' => '', 'entreprise' => Entreprise::donnees(),
        ])->render();
        $this->assertStringContainsString('BON DE RETOUR', $html);
        $this->assertStringNotContainsString('ANNULÉ', $html);
    }

    public function test_assistant_prerempli_depuis_la_fiche(): void
    {
        $vente = $this->vente();

        $this->get(route('retours.create', ['vente' => $vente->id]))
            ->assertOk()
            ->assertSee('Nouveau retour client')
            ->assertSee('Produit défectueux')
            ->assertSee($vente->numero);

        $achat = $this->achat();
        $this->get(route('retours.create', ['achat' => $achat->id]))->assertOk()->assertSee('Nouveau retour fournisseur')->assertSee('Livraison en excès');
    }

    public function test_annulation_par_la_route(): void
    {
        $vente = $this->vente();
        $retour = app(RetourService::class)->creerClient($vente, [$this->ciment->id => 1], 'Erreur de produit');

        $this->from(route('retours.show', $retour))->post(route('retours.annuler', $retour), ['motif' => ''])->assertSessionHasErrors('motif');
        $this->from(route('retours.show', $retour))->post(route('retours.annuler', $retour), ['motif' => 'Retour saisi par erreur'])
            ->assertRedirect(route('retours.show', $retour))
            ->assertSessionHas('succes', "Retour {$retour->numero} annulé : stock et reste à payer rétablis.");

        $this->assertSame(StatutRetour::Annule, $retour->fresh()->statut);
        $this->get(route('retours.show', $retour))->assertSee('Retour annulé')->assertSee('Retour saisi par erreur')->assertDontSee('Annuler le retour ?');
        $this->post(route('retours.annuler', $retour), ['motif' => 'Encore une fois'])->assertSessionHas('erreur');
    }

    public function test_droits(): void
    {
        $retour = app(RetourService::class)->creerClient($this->vente(), [$this->ciment->id => 1], 'Erreur de produit');

        foreach ([RoleSeeder::VENDEUR, RoleSeeder::MAGASINIER] as $role) {
            $this->actingAs($this->avecRole($role));
            $this->get(route('retours.clients'))->assertForbidden();
            $this->get(route('retours.create'))->assertForbidden();
            $this->get(route('retours.show', $retour))->assertForbidden();
            $this->postJson(route('retours.store'), [])->assertForbidden();
            $this->post(route('retours.annuler', $retour), ['motif' => 'Tentative'])->assertForbidden();
        }

        auth()->logout();
        $this->get(route('retours.clients'))->assertRedirect(route('connexion'));
    }
}
