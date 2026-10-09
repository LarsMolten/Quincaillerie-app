<?php

namespace Tests\Feature\Produits;

use App\Enums\TypeMouvementStock;
use App\Models\Achat;
use App\Models\LigneAchat;
use App\Models\LigneVente;
use App\Models\Produit;
use App\Models\Vente;
use App\Services\MouvementStockService;

class FicheEtEtiquettesTest extends ProduitsTestCase
{
    public function test_fiche_bento_complete(): void
    {
        $produit = Produit::factory()->create([
            'nom' => 'Ciment Holcim', 'code_barres' => '2000000000015',
            'stock_actuel' => 0, 'stock_minimum' => 10, 'prix_achat' => 33000, 'prix_vente' => 37000,
        ]);
        app(MouvementStockService::class)->enregistrer($produit, TypeMouvementStock::Achat, 15, null, 'Réception');

        $vente = Vente::factory()->create(['numero' => 'VTE-2026-00042', 'date_vente' => now()->subDays(2)]);
        LigneVente::factory()->for($vente)->for($produit)->create(['quantite' => 4, 'prix_unitaire' => 37000, 'total' => 148000]);
        $achat = Achat::factory()->create(['numero' => 'ACH-2026-00007']);
        LigneAchat::factory()->for($achat)->for($produit)->create(['quantite' => 20]);

        $this->get(route('produits.show', $produit))
            ->assertOk()
            ->assertSee('Ciment Holcim')
            ->assertSee('role="meter"', false)
            ->assertSee('En stock') // 15 au-dessus du minimum de 10
            ->assertSee('Vendu sur 30 jours')
            ->assertSee('<polyline', false)
            ->assertSee('VTE-2026-00042')
            ->assertSee('ACH-2026-00007')
            ->assertSee('Réception')
            ->assertSee('2000000000015')
            ->assertSee('fill="currentColor"', false)
            ->assertSee("4\u{00A0}000\u{00A0}Ar", false); // marge unitaire
    }

    public function test_badge_selon_l_etat_du_stock(): void
    {
        $this->get(route('produits.show', Produit::factory()->create(['stock_actuel' => 50, 'stock_minimum' => 10])))->assertSee('En stock');
        $this->get(route('produits.show', Produit::factory()->stockFaible()->create()))->assertSee('Stock faible');
        $this->get(route('produits.show', Produit::factory()->enRupture()->create()))->assertSee('Rupture');
    }

    public function test_produit_inexistant(): void
    {
        $this->get('/produits/999999')->assertNotFound();
    }

    public function test_etiquettes_pdf(): void
    {
        $produits = Produit::factory()->count(2)->create();

        $reponse = $this->get(route('produits.etiquettes', ['produits' => $produits->pluck('id')->all(), 'quantite' => 5]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $reponse->getContent());
    }

    public function test_etiquettes_validation(): void
    {
        $produit = Produit::factory()->create();

        $this->get(route('produits.etiquettes', ['produits' => [$produit->id], 'quantite' => 500]))
            ->assertSessionHasErrors(['quantite' => 'Au plus 100 étiquettes par produit.']);
        $this->get(route('produits.etiquettes'))
            ->assertSessionHasErrors(['produits' => 'Sélectionnez au moins un produit.']);
    }
}
