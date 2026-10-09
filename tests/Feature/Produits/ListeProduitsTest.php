<?php

namespace Tests\Feature\Produits;

use App\Models\Categorie;
use App\Models\Produit;

class ListeProduitsTest extends ProduitsTestCase
{
    public function test_recherche_par_nom_reference_ou_code_barres(): void
    {
        Produit::factory()->create(['nom' => 'Ciment Holcim', 'reference' => 'CIM-050', 'code_barres' => '2000000000015']);
        Produit::factory()->create(['nom' => 'Tôle ondulée', 'reference' => 'TOL-001']);

        foreach (['holcim', 'CIM-0', '2000000000015'] as $terme) {
            $this->get(route('produits.index', ['recherche' => $terme]))
                ->assertSee('Ciment Holcim')
                ->assertDontSee('Tôle ondulée');
        }
    }

    public function test_fragment_sans_layout(): void
    {
        Produit::factory()->create(['nom' => 'Ciment Holcim']);

        $contenu = $this->get(route('produits.index', ['recherche' => 'ciment']), ['X-Fragment' => 'liste'])
            ->assertOk()->assertSee('Ciment Holcim')->getContent();

        $this->assertStringNotContainsString('<!DOCTYPE', $contenu);
    }

    public function test_puces_categorie_statut_et_etat_du_stock(): void
    {
        $plomberie = Categorie::factory()->create(['nom' => 'Robinetterie']);
        Produit::factory()->for($plomberie)->create(['nom' => 'Mitigeur', 'stock_actuel' => 50, 'stock_minimum' => 5]);
        Produit::factory()->create(['nom' => 'Gravier', 'stock_actuel' => 3, 'stock_minimum' => 10]);
        Produit::factory()->enRupture()->create(['nom' => 'Brouette']);
        Produit::factory()->inactif()->create(['nom' => 'Ancien modèle']);

        $voir = fn (array $filtres) => $this->get(route('produits.index', $filtres));

        $voir(['categorie' => $plomberie->id])->assertSee('Mitigeur')->assertDontSee('Gravier');
        $voir(['stock' => 'normal'])->assertSee('Mitigeur')->assertDontSee('Brouette');
        $voir(['stock' => 'faible'])->assertSee('Gravier')->assertDontSee('Mitigeur');
        $voir(['stock' => 'rupture'])->assertSee('Brouette')->assertDontSee('Mitigeur');

        // Inactifs masqués par défaut
        $voir([])->assertDontSee('Ancien modèle');
        $voir(['statut' => 'inactifs'])->assertSee('Ancien modèle')->assertDontSee('Mitigeur');
        $voir(['statut' => 'tous'])->assertSee('Ancien modèle')->assertSee('Mitigeur');
    }

    public function test_tri_par_prix_et_par_categorie(): void
    {
        Produit::factory()->for(Categorie::factory()->create(['nom' => 'Zinguerie']))->create(['nom' => 'Gouttière', 'prix_vente' => 28000]);
        Produit::factory()->for(Categorie::factory()->create(['nom' => 'Accessoires']))->create(['nom' => 'Cadenas', 'prix_vente' => 13000]);

        $this->get(route('produits.index', ['tri' => 'prix_vente', 'ordre' => 'desc']))->assertSeeInOrder(['Gouttière', 'Cadenas']);
        $this->get(route('produits.index', ['tri' => 'categorie', 'ordre' => 'asc']))->assertSeeInOrder(['Cadenas', 'Gouttière']);
        $this->get(route('produits.index', ['tri' => 'categorie', 'recherche' => 'cad']))->assertOk()->assertSee('Cadenas');
    }

    public function test_vue_grille_memorisee(): void
    {
        Produit::factory()->create(['nom' => 'Cadenas laiton']);

        $this->get(route('produits.index', ['vue' => 'grille']))->assertSee('aspect-[4/3]', false);
        $this->get(route('produits.index'))->assertSee('aspect-[4/3]', false);
        $this->get(route('produits.index', ['vue' => 'tableau']))->assertSee('Liste des produits')->assertDontSee('aspect-[4/3]', false);
    }

    public function test_pagination_et_badge_d_etat_du_stock(): void
    {
        Produit::factory()->count(16)->create();
        Produit::factory()->enRupture()->create(['nom' => 'AAA Rupture']);

        $this->get(route('produits.index'))
            ->assertSee('aria-label="Pagination"', false)
            ->assertSee('Rupture')
            ->assertSee('bg-danger-doux', false);
    }

    public function test_synthese_et_etat_vide(): void
    {
        $this->get(route('produits.index'))
            ->assertSee('Aucun produit')
            ->assertSee('Ajouter un produit')
            ->assertSee('Valeur du stock (achat)');

        $this->get(route('produits.index', ['recherche' => 'rien']))->assertSee('Effacer les filtres');
    }
}
