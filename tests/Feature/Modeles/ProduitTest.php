<?php

namespace Tests\Feature\Modeles;

use App\Enums\EtatStock;
use App\Models\Categorie;
use App\Models\LigneVente;
use App\Models\MouvementStock;
use App\Models\Produit;
use App\Models\Unite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProduitTest extends TestCase
{
    use RefreshDatabase;

    public function test_etat_stock_selon_le_stock_minimum(): void
    {
        $this->assertSame(EtatStock::Normal, Produit::factory()->make(['stock_actuel' => 50, 'stock_minimum' => 10])->etat_stock);
        $this->assertSame(EtatStock::Faible, Produit::factory()->make(['stock_actuel' => 10, 'stock_minimum' => 10])->etat_stock);
        $this->assertSame(EtatStock::Rupture, Produit::factory()->make(['stock_actuel' => 0])->etat_stock);
        $this->assertSame(EtatStock::Rupture, Produit::factory()->make(['stock_actuel' => -2])->etat_stock);
        $this->assertSame('alerte', EtatStock::Faible->couleur());
    }

    public function test_scopes_de_stock_et_d_activite(): void
    {
        $normal = Produit::factory()->create(['stock_actuel' => 50, 'stock_minimum' => 10]);
        $faible = Produit::factory()->stockFaible()->create();
        $rupture = Produit::factory()->enRupture()->create();
        $inactif = Produit::factory()->inactif()->create();

        $this->assertEquals([$rupture->id], Produit::enRupture()->pluck('id')->all());
        $this->assertEquals([$faible->id], Produit::stockFaible()->pluck('id')->all());
        $this->assertNotContains($inactif->id, Produit::actif()->pluck('id'));
        $this->assertContains($normal->id, Produit::actif()->pluck('id'));
    }

    public function test_recherche_par_nom_reference_ou_code_barres(): void
    {
        $ciment = Produit::factory()->create([
            'nom' => 'Ciment Holcim 50 kg',
            'reference' => 'CIM-050',
            'code_barres' => '6111234567890',
        ]);
        Produit::factory()->create(['nom' => 'Tôle ondulée', 'reference' => 'TOL-001']);

        $this->assertEquals([$ciment->id], Produit::recherche('holcim')->pluck('id')->all());
        $this->assertEquals([$ciment->id], Produit::recherche('CIM-0')->pluck('id')->all());
        $this->assertEquals([$ciment->id], Produit::recherche('6111234567890')->pluck('id')->all());
        $this->assertCount(2, Produit::recherche('  ')->get());
    }

    public function test_le_stock_actuel_n_est_pas_assignable_en_masse(): void
    {
        $produit = Produit::create([
            'reference' => 'CIM-001',
            'nom' => 'Ciment',
            'categorie_id' => Categorie::factory()->create()->id,
            'unite_id' => Unite::factory()->create()->id,
            'prix_achat' => 32000,
            'prix_vente' => 35000,
            'stock_actuel' => 999,
        ]);

        $this->assertEquals(0, $produit->fresh()->stock_actuel);
    }

    public function test_relations_du_produit(): void
    {
        $produit = Produit::factory()->create();
        LigneVente::factory()->for($produit)->create();
        MouvementStock::factory()->for($produit)->create();

        $this->assertInstanceOf(Categorie::class, $produit->categorie);
        $this->assertInstanceOf(Unite::class, $produit->unite);
        $this->assertCount(1, $produit->lignesVente);
        $this->assertCount(1, $produit->mouvementsStock);
        $this->assertTrue($produit->categorie->produits->contains($produit));
    }

    public function test_un_produit_supprime_reste_visible_dans_l_historique(): void
    {
        $ligne = LigneVente::factory()->create();
        $ligne->produit->delete();

        $this->assertSoftDeleted($ligne->produit);
        $this->assertNotNull($ligne->fresh()->produit);
    }
}
