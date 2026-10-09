<?php

namespace Tests\Feature\Produits;

use App\Models\Produit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ModificationProduitTest extends ProduitsTestCase
{
    public function test_modification_sans_toucher_au_stock(): void
    {
        $produit = Produit::factory()->create(['stock_actuel' => 50, 'reference' => 'CIM-01']);

        $this->from(route('produits.show', $produit))
            ->put(route('produits.update', $produit), $this->donnees([
                '_formulaire' => $produit->id,
                'nom' => 'Ciment renommé',
                'stock_actuel' => '999',
            ]))
            ->assertRedirect(route('produits.show', $produit))
            ->assertSessionHas('succes', 'Produit « Ciment renommé » modifié.');

        $produit->refresh();
        $this->assertSame('Ciment renommé', $produit->nom);
        $this->assertEquals(50, $produit->stock_actuel);
        $this->assertSame('CIM-01', $produit->reference, 'Une référence vide garde la référence existante.');
    }

    public function test_le_stock_initial_est_refuse_en_modification(): void
    {
        $produit = Produit::factory()->create();

        $this->put(route('produits.update', $produit), $this->donnees(['stock_initial' => '10']))
            ->assertSessionHasErrors('stock_initial');
    }

    public function test_une_categorie_devenue_inactive_reste_acceptee_pour_le_produit(): void
    {
        $produit = Produit::factory()->for($this->categorie)->create();
        $this->categorie->update(['actif' => false]);

        $this->put(route('produits.update', $produit), $this->donnees(['nom' => 'Toujours valide']))
            ->assertSessionHasNoErrors();
    }

    public function test_remplacement_et_retrait_de_la_photo(): void
    {
        Storage::fake('local');
        $produit = Produit::factory()->create();

        $this->put(route('produits.update', $produit), $this->donnees(['photo' => UploadedFile::fake()->image('a.png', 300, 300)]));
        $premiere = $produit->fresh()->image;
        Storage::disk('local')->assertExists($premiere);

        $this->put(route('produits.update', $produit), $this->donnees(['photo' => UploadedFile::fake()->image('b.png', 300, 300)]));
        $seconde = $produit->fresh()->image;
        Storage::disk('local')->assertMissing($premiere);
        Storage::disk('local')->assertExists($seconde);

        $this->put(route('produits.update', $produit), $this->donnees(['retirer_photo' => '1']));
        $this->assertNull($produit->fresh()->image);
        Storage::disk('local')->assertMissing($seconde);
    }

    public function test_desactivation_reactivation_journalisees_et_exclusion_des_actifs(): void
    {
        $produit = Produit::factory()->create(['nom' => 'Robinet']);

        $this->patch(route('produits.statut', $produit))
            ->assertSessionHas('succes', 'Produit « Robinet » désactivé : il n\'est plus proposé en vente ni en achat.');
        $this->assertFalse($produit->fresh()->actif);
        // Les ventes et les achats ne proposent que Produit::actif()
        $this->assertFalse(Produit::actif()->whereKey($produit->id)->exists());

        $this->patch(route('produits.statut', $produit));
        $this->assertTrue($produit->fresh()->actif);

        $this->assertDatabaseHas('journal_activites', ['action' => 'produit.desactive', 'modele' => 'produit', 'modele_id' => $produit->id]);
        $this->assertDatabaseHas('journal_activites', ['action' => 'produit.reactive', 'modele_id' => $produit->id]);
    }

    public function test_aucune_suppression_possible(): void
    {
        $produit = Produit::factory()->create();

        $this->delete('/produits/'.$produit->id)->assertStatus(405);
        $this->assertNotSoftDeleted($produit);
    }
}
