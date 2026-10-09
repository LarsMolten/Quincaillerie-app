<?php

namespace Tests\Feature\Services;

use App\Enums\SensMouvement;
use App\Enums\TypeMouvementStock;
use App\Exceptions\StockInsuffisantException;
use App\Models\MouvementStock;
use App\Models\Parametre;
use App\Models\Produit;
use App\Models\Utilisateur;
use App\Models\Vente;
use App\Services\MouvementStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Service de stock : seule porte d'entrée pour modifier produits.stock_actuel.
 * (La vraie concurrence entre processus est testée dans ConcurrenceStockTest, sur MySQL.)
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

    /** Produit dont l'historique de stock commence par un vrai mouvement (comme dans l'application). */
    private function produitAvecStock(float $stock, array $attributs = []): Produit
    {
        $produit = Produit::factory()->create(['stock_actuel' => 0, ...$attributs]);

        if ($stock > 0) {
            $this->service->enregistrer($produit, TypeMouvementStock::AjustementPositif, $stock, null, 'Stock initial');
        }

        return $produit;
    }

    public static function typesEntrants(): array
    {
        return [
            'achat' => [TypeMouvementStock::Achat],
            'retour client' => [TypeMouvementStock::RetourClient],
            'ajustement positif' => [TypeMouvementStock::AjustementPositif],
        ];
    }

    public static function typesSortants(): array
    {
        return [
            'vente' => [TypeMouvementStock::Vente],
            'retour fournisseur' => [TypeMouvementStock::RetourFournisseur],
            'ajustement négatif' => [TypeMouvementStock::AjustementNegatif],
            'perte' => [TypeMouvementStock::Perte],
        ];
    }

    #[DataProvider('typesEntrants')]
    public function test_une_entree_augmente_le_stock(TypeMouvementStock $type): void
    {
        $produit = $this->produitAvecStock(10);

        $mouvement = $this->service->enregistrer($produit, $type, 5.5, null, 'Réception');

        $this->assertEquals(15.5, $produit->fresh()->stock_actuel);
        $this->assertEquals(15.5, $produit->stock_actuel, 'L\'instance passée reflète le nouveau stock.');
        $this->assertSame($type, $mouvement->type);
        $this->assertSame(SensMouvement::Entree, $mouvement->sens);
        $this->assertEquals(10, $mouvement->stock_avant);
        $this->assertEquals(15.5, $mouvement->stock_apres);
        $this->assertSame('Réception', $mouvement->motif);
        $this->assertSame(auth()->id(), $mouvement->utilisateur_id);
    }

    #[DataProvider('typesSortants')]
    public function test_une_sortie_diminue_le_stock(TypeMouvementStock $type): void
    {
        $produit = $this->produitAvecStock(10);

        $mouvement = $this->service->enregistrer($produit, $type, 4);

        $this->assertEquals(6, $produit->fresh()->stock_actuel);
        $this->assertSame(SensMouvement::Sortie, $mouvement->sens);
        $this->assertEquals(10, $mouvement->stock_avant);
        $this->assertEquals(6, $mouvement->stock_apres);
    }

    public function test_la_reference_polymorphe_est_conservee(): void
    {
        $produit = $this->produitAvecStock(10);
        $vente = Vente::factory()->create();

        $mouvement = $this->service->enregistrer($produit, TypeMouvementStock::Vente, 1, $vente);

        $this->assertSame('vente', $mouvement->reference_type);
        $this->assertTrue($mouvement->reference->is($vente));
    }

    public function test_quantites_decimales_sans_residu(): void
    {
        $produit = $this->produitAvecStock(2.5);

        $this->service->enregistrer($produit, TypeMouvementStock::Achat, 0.1);
        $this->service->enregistrer($produit, TypeMouvementStock::Achat, 0.2);

        // Valeur exacte en base : pas de résidu flottant (2.8000000000000003)
        $this->assertSame(2.8, round((float) DB::table('produits')->where('id', $produit->id)->value('stock_actuel'), 10));
        $this->assertSame('2.800', $produit->fresh()->stock_actuel);
        $this->assertTrue($this->service->verifierCoherence($produit)->isEmpty());
    }

    public function test_sortir_exactement_le_stock_disponible(): void
    {
        $produit = $this->produitAvecStock(3);

        $this->service->enregistrer($produit, TypeMouvementStock::Vente, 3);

        $this->assertEquals(0, $produit->fresh()->stock_actuel);
    }

    public function test_stock_insuffisant_refuse_sans_rien_modifier(): void
    {
        $produit = $this->produitAvecStock(3, ['nom' => 'Ciment 50 kg']);
        $mouvementsAvant = MouvementStock::count();

        try {
            $this->service->enregistrer($produit, TypeMouvementStock::Vente, 5);
            $this->fail('Une exception StockInsuffisantException était attendue.');
        } catch (StockInsuffisantException $exception) {
            $this->assertSame('Stock insuffisant pour « Ciment 50 kg » : 3 disponible(s), 5 demandé(s).', $exception->getMessage());
        }

        $this->assertEquals(3, $produit->fresh()->stock_actuel);
        $this->assertSame($mouvementsAvant, MouvementStock::count());
    }

    public function test_stock_negatif_autorise_par_parametre_et_journalise(): void
    {
        $produit = $this->produitAvecStock(2);
        Parametre::create(['cle' => 'stock_negatif_autorise', 'valeur' => '1']);

        $mouvement = $this->service->enregistrer($produit, TypeMouvementStock::Vente, 5);

        $this->assertEquals(-3, $produit->fresh()->stock_actuel);
        $this->assertEquals(-3, $mouvement->stock_apres);
        $this->assertDatabaseHas('journal_activites', ['action' => 'stock.negatif', 'modele' => 'produit', 'modele_id' => $produit->id]);
    }

    public function test_stock_negatif_refuse_si_parametre_desactive(): void
    {
        $produit = $this->produitAvecStock(2);
        $parametre = Parametre::create(['cle' => 'stock_negatif_autorise', 'valeur' => '1']);
        $parametre->update(['valeur' => '0']); // le cache est invalidé à la modification

        $this->expectException(StockInsuffisantException::class);
        $this->service->enregistrer($produit, TypeMouvementStock::Vente, 5);
    }

    public function test_le_stock_est_relu_en_base_et_non_sur_l_instance_perimee(): void
    {
        $produit = $this->produitAvecStock(10);
        $perimee = Produit::find($produit->id);

        // Un autre poste vend 8 unités pendant que $perimee est encore en mémoire
        $this->service->enregistrer($produit, TypeMouvementStock::Vente, 8);
        $this->assertEquals(10, $perimee->stock_actuel);

        $this->expectException(StockInsuffisantException::class);
        $this->service->enregistrer($perimee, TypeMouvementStock::Vente, 5); // 2 restants réellement
    }

    public function test_une_transaction_englobante_annulee_annule_aussi_le_mouvement(): void
    {
        $produit = $this->produitAvecStock(10);
        $mouvementsAvant = MouvementStock::count();

        try {
            DB::transaction(function () use ($produit) {
                $this->service->enregistrer($produit, TypeMouvementStock::Vente, 4);
                throw new RuntimeException('Échec de la vente (ex. paiement refusé)');
            });
        } catch (RuntimeException) {
        }

        $this->assertEquals(10, $produit->fresh()->stock_actuel);
        $this->assertSame($mouvementsAvant, MouvementStock::count());
    }

    public static function quantitesInvalides(): array
    {
        return ['zéro' => [0], 'négative' => [-2], 'arrondie à zéro' => [0.0004], 'NaN' => [NAN], 'infinie' => [INF]];
    }

    #[DataProvider('quantitesInvalides')]
    public function test_quantite_invalide_refusee(float $quantite): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La quantité d\'un mouvement de stock doit être positive.');

        $this->service->enregistrer(Produit::factory()->create(), TypeMouvementStock::Achat, $quantite);
    }

    public function test_un_utilisateur_connecte_est_obligatoire(): void
    {
        auth()->logout();

        $this->expectException(RuntimeException::class);

        $this->service->enregistrer(Produit::factory()->create(), TypeMouvementStock::Achat, 1);
    }

    public function test_coherence_sans_anomalie_apres_une_serie_de_mouvements(): void
    {
        $produit = $this->produitAvecStock(20);
        $this->service->enregistrer($produit, TypeMouvementStock::Vente, 3);
        $this->service->enregistrer($produit, TypeMouvementStock::Achat, 10);
        $this->service->enregistrer($produit, TypeMouvementStock::Perte, 1.5);
        $this->produitAvecStock(5);

        $this->assertTrue($this->service->verifierCoherence()->isEmpty());
    }

    public function test_coherence_detecte_un_stock_modifie_directement(): void
    {
        $produit = $this->produitAvecStock(20, ['reference' => 'CIM-001']);
        $sain = $this->produitAvecStock(5);

        // Modification interdite, simulée pour le test
        DB::table('produits')->where('id', $produit->id)->update(['stock_actuel' => 23]);

        $anomalies = $this->service->verifierCoherence();

        $this->assertCount(1, $anomalies);
        $this->assertSame('CIM-001', $anomalies[0]['reference']);
        $this->assertEquals(23, $anomalies[0]['stock_actuel']);
        $this->assertEquals(20, $anomalies[0]['stock_calcule']);
        $this->assertEquals(3, $anomalies[0]['ecart']);
        $this->assertTrue($this->service->verifierCoherence($sain)->isEmpty());
    }

    public function test_coherence_detecte_une_chaine_de_mouvements_cassee(): void
    {
        $produit = $this->produitAvecStock(20);
        $vente = $this->service->enregistrer($produit, TypeMouvementStock::Vente, 5);
        $this->service->enregistrer($produit, TypeMouvementStock::Vente, 5);

        DB::table('mouvements_stock')->where('id', $vente->id)->update(['stock_avant' => 18]);

        $anomalies = $this->service->verifierCoherence($produit);

        $this->assertCount(1, $anomalies);
        $this->assertSame([$vente->id], $anomalies[0]['ruptures_chaine']);
        $this->assertEquals(0, $anomalies[0]['ecart'], 'La somme reste juste : seule la chaîne est cassée.');
    }

    public function test_commande_stock_verifier(): void
    {
        $produit = $this->produitAvecStock(20, ['reference' => 'CIM-001', 'nom' => 'Ciment 50 kg']);
        $this->produitAvecStock(5);

        $this->artisan('stock:verifier')
            ->expectsOutputToContain('Stock cohérent : 2 produit(s) vérifié(s), aucun écart.')
            ->assertExitCode(0);

        DB::table('produits')->where('id', $produit->id)->update(['stock_actuel' => 17]);

        $this->artisan('stock:verifier')
            ->expectsTable(
                ['Référence', 'Produit', 'Stock enregistré', 'Stock recalculé', 'Écart', 'Mouvements incohérents'],
                [['CIM-001', 'Ciment 50 kg', '17', '20', '-3', '—']],
            )
            ->expectsOutputToContain('1 anomalie(s) détectée(s).')
            ->assertExitCode(1);

        $this->artisan('stock:verifier', ['--produit' => 'CIM-001'])->assertExitCode(1);
        $this->artisan('stock:verifier', ['--produit' => (string) $produit->id])->assertExitCode(1);
        $this->artisan('stock:verifier', ['--produit' => 'INCONNU'])
            ->expectsOutputToContain('Produit introuvable : « INCONNU ».')
            ->assertExitCode(2);
    }
}
