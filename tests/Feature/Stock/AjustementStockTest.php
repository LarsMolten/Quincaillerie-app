<?php

namespace Tests\Feature\Stock;

use App\Enums\TypeMouvementStock;
use App\Models\Droit;
use App\Models\JournalActivite;
use App\Models\MouvementStock;
use App\Models\Produit;
use App\Models\Role;
use App\Models\Utilisateur;
use App\Services\MouvementStockService;
use Database\Seeders\DroitSeeder;
use Database\Seeders\ParametreSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AjustementStockTest extends TestCase
{
    use RefreshDatabase;

    private Produit $ciment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed([RoleSeeder::class, DroitSeeder::class, ParametreSeeder::class]);
        $this->actingAs(Utilisateur::factory()->create(['role_id' => Role::where('nom', RoleSeeder::MAGASINIER)->firstOrFail()->id]));
        $this->ciment = Produit::factory()->create(['nom' => 'Ciment 50 kg', 'reference' => 'CIM-050', 'stock_actuel' => 0, 'actif' => true]);
        app(MouvementStockService::class)->enregistrer($this->ciment, TypeMouvementStock::Achat, 20);
    }

    public function test_perte_et_ajustements_modifient_le_stock_et_sont_journalises(): void
    {
        $this->postJson(route('stock.ajustements.store'), ['produit_id' => $this->ciment->id, 'type' => 'perte', 'quantite' => '2', 'motif' => 'Sacs percés à la livraison'])
            ->assertCreated()
            ->assertJsonPath('message', '« Ciment 50 kg » : stock 20 → 18 (perte).');
        $this->postJson(route('stock.ajustements.store'), ['produit_id' => $this->ciment->id, 'type' => 'ajustement_positif', 'quantite' => '1,5', 'motif' => 'Retrouvé en réserve'])
            ->assertCreated();
        $this->postJson(route('stock.ajustements.store'), ['produit_id' => $this->ciment->id, 'type' => 'ajustement_negatif', 'quantite' => '0.5', 'motif' => 'Erreur de saisie'])
            ->assertCreated();

        $this->assertEquals(19, $this->ciment->fresh()->stock_actuel);
        $perte = MouvementStock::where('type', 'perte')->sole();
        $this->assertSame('Sacs percés à la livraison', $perte->motif);
        $this->assertNull($perte->reference_type);
        $this->assertSame(3, JournalActivite::where('action', 'stock.ajuste')->where('modele', 'produit')->count());

        $this->get(route('stock.sorties'))->assertSee('Sacs percés à la livraison')->assertSee('Saisie manuelle');
    }

    public function test_validation_et_stock_insuffisant(): void
    {
        $base = ['produit_id' => $this->ciment->id, 'type' => 'perte', 'quantite' => '1', 'motif' => 'Sac déchiré'];

        $this->postJson(route('stock.ajustements.store'), [...$base, 'motif' => ''])->assertStatus(422)->assertJsonValidationErrors(['motif' => 'Indiquez le motif de l\'ajustement.']);
        $this->postJson(route('stock.ajustements.store'), [...$base, 'motif' => 'abc'])->assertStatus(422)->assertJsonValidationErrors('motif');
        $this->postJson(route('stock.ajustements.store'), [...$base, 'quantite' => '0'])->assertStatus(422)->assertJsonValidationErrors(['quantite' => 'La quantité doit être supérieure à 0.']);
        $this->postJson(route('stock.ajustements.store'), [...$base, 'type' => 'vente'])->assertStatus(422)->assertJsonValidationErrors('type');
        $this->postJson(route('stock.ajustements.store'), [...$base, 'produit_id' => null])->assertStatus(422)->assertJsonValidationErrors(['produit_id' => 'Choisissez le produit.']);

        $this->postJson(route('stock.ajustements.store'), [...$base, 'quantite' => '25'])
            ->assertStatus(422)
            ->assertJsonPath('errors.quantite.0', 'Stock insuffisant pour « Ciment 50 kg » : 20 disponible(s), 25 demandé(s).');
        $this->assertEquals(20, $this->ciment->fresh()->stock_actuel);
    }

    public function test_recherche_de_produit_pour_la_modale(): void
    {
        $this->getJson(route('stock.produits', ['q' => 'CIM']))->assertOk()
            ->assertJsonPath('0.nom', 'Ciment 50 kg')
            ->assertJsonPath('0.stock', 20);
    }

    public function test_consultation_sans_droit_d_ajuster(): void
    {
        $lecteur = Utilisateur::factory()->create();
        $lecteur->role->droits()->attach(Droit::where('code', 'stock.voir')->value('id'));
        $this->actingAs($lecteur);

        $this->get(route('stock.sorties'))->assertOk()->assertDontSee('Perte ou ajustement');
        $this->getJson(route('stock.produits', ['q' => 'CIM']))->assertForbidden();
        $this->postJson(route('stock.ajustements.store'), ['produit_id' => $this->ciment->id, 'type' => 'perte', 'quantite' => '1', 'motif' => 'Sac déchiré'])->assertForbidden();
    }
}
