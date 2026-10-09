<?php

namespace Tests\Feature\Produits;

use App\Models\Produit;
use App\Models\Utilisateur;
use Database\Seeders\RoleSeeder;

class DroitsProduitsTest extends ProduitsTestCase
{
    public function test_le_vendeur_consulte_mais_ne_modifie_pas(): void
    {
        $produit = Produit::factory()->create();
        $this->actingAs($this->avecRole(RoleSeeder::VENDEUR));

        $this->get(route('produits.index'))->assertOk()->assertDontSee('Nouveau produit');
        $this->get(route('produits.show', $produit))->assertOk()->assertDontSee('Désactiver');
        $this->post(route('produits.store'), $this->donnees())->assertForbidden();
        $this->put(route('produits.update', $produit), $this->donnees())->assertForbidden();
        $this->patch(route('produits.statut', $produit))->assertForbidden();
        $this->get(route('produits.code-barres'))->assertForbidden();
    }

    public function test_le_magasinier_a_tous_les_droits_produits(): void
    {
        $produit = Produit::factory()->create();
        $this->actingAs($this->avecRole(RoleSeeder::MAGASINIER));

        $this->get(route('produits.index'))->assertOk()->assertSee('Nouveau produit');
        $this->post(route('produits.store'), $this->donnees())->assertSessionHasNoErrors()->assertRedirect();
        $this->put(route('produits.update', $produit), $this->donnees(['nom' => 'Modifié']))->assertSessionHasNoErrors();
        $this->patch(route('produits.statut', $produit))->assertRedirect();
    }

    public function test_un_role_sans_droit_est_refuse(): void
    {
        $produit = Produit::factory()->create();
        $this->actingAs(Utilisateur::factory()->create());

        $this->get(route('produits.index'))->assertForbidden();
        $this->get(route('produits.show', $produit))->assertForbidden();
        $this->get(route('produits.photo', $produit))->assertForbidden();
        $this->get(route('produits.etiquettes', ['produits' => [$produit->id]]))->assertForbidden();
    }
}
