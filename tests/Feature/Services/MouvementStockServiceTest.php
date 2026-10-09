<?php

namespace Tests\Feature\Services;

use App\Enums\SensMouvement;
use App\Enums\TypeMouvementStock;
use App\Exceptions\StockInsuffisantException;
use App\Models\Produit;
use App\Models\Utilisateur;
use App\Models\Vente;
use App\Services\MouvementStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

/**
 * Socle du service de stock (complété au prompt 12 : contrôle de cohérence, concurrence).
 */
class MouvementStockServiceTest extends TestCase
{
    use RefreshDatabase;

    private MouvementStockService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(MouvementStockService::class);
        $this->actingAs(Utilisateur::factory()->create());
    }

    public function test_une_entree_augmente_le_stock_et_trace_le_mouvement(): void
    {
        $produit = Produit::factory()->create(['stock_actuel' => 10]);

        $mouvement = $this->service->enregistrer($produit, TypeMouvementStock::Achat, 5.5, null, 'Réception');

        $this->assertEquals(15.5, $produit->fresh()->stock_actuel);
        $this->assertEquals(15.5, $produit->stock_actuel);
        $this->assertSame(SensMouvement::Entree, $mouvement->sens);
        $this->assertEquals(10, $mouvement->stock_avant);
        $this->assertEquals(15.5, $mouvement->stock_apres);
        $this->assertSame(auth()->id(), $mouvement->utilisateur_id);
    }

    public function test_une_sortie_diminue_le_stock_et_garde_la_reference(): void
    {
        $produit = Produit::factory()->create(['stock_actuel' => 10]);
        $vente = Vente::factory()->create();

        $mouvement = $this->service->enregistrer($produit, TypeMouvementStock::Vente, 4, $vente);

        $this->assertEquals(6, $produit->fresh()->stock_actuel);
        $this->assertSame('vente', $mouvement->reference_type);
        $this->assertTrue($mouvement->reference->is($vente));
    }

    public function test_une_sortie_superieure_au_stock_est_refusee_sans_rien_modifier(): void
    {
        $produit = Produit::factory()->create(['nom' => 'Ciment 50 kg', 'stock_actuel' => 3]);

        try {
            $this->service->enregistrer($produit, TypeMouvementStock::Vente, 5);
            $this->fail('Une exception était attendue.');
        } catch (StockInsuffisantException $exception) {
            $this->assertSame('Stock insuffisant pour « Ciment 50 kg » : 3 disponible(s), 5 demandé(s).', $exception->getMessage());
        }

        $this->assertEquals(3, $produit->fresh()->stock_actuel);
        $this->assertSame(0, $produit->mouvementsStock()->count());
    }

    public function test_la_quantite_doit_etre_positive(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->enregistrer(Produit::factory()->create(), TypeMouvementStock::Achat, 0);
    }

    public function test_un_utilisateur_connecte_est_obligatoire(): void
    {
        auth()->logout();

        $this->expectException(RuntimeException::class);

        $this->service->enregistrer(Produit::factory()->create(), TypeMouvementStock::Achat, 1);
    }
}
