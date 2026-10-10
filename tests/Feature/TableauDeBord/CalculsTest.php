<?php

namespace Tests\Feature\TableauDeBord;

use App\Enums\CategorieDepense;
use App\Enums\ModePaiement;
use App\Enums\TypeMouvementStock;
use App\Models\Client;
use App\Models\Depense;
use App\Models\Fournisseur;
use App\Models\Produit;
use App\Models\Role;
use App\Models\Utilisateur;
use App\Rapports\TableauDeBord;
use App\Services\AchatService;
use App\Services\MouvementStockService;
use App\Services\RetourService;
use App\Services\VenteService;
use Database\Seeders\ClientComptoirSeeder;
use Database\Seeders\DroitSeeder;
use Database\Seeders\ParametreSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CalculsTest extends TestCase
{
    use RefreshDatabase;

    private TableauDeBord $tableau;

    private Produit $ciment;

    private Produit $clous;

    private Client $comptoir;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-12 15:00:00');
        Cache::flush();
        $this->seed([RoleSeeder::class, DroitSeeder::class, ParametreSeeder::class, ClientComptoirSeeder::class]);
        $this->actingAs(Utilisateur::factory()->create(['role_id' => Role::where('nom', RoleSeeder::RESPONSABLE)->firstOrFail()->id]));
        $this->tableau = app(TableauDeBord::class);
        $this->comptoir = Client::where('nom', Client::COMPTOIR)->firstOrFail();

        // Ciment : acheté 30 000, vendu 40 000 ; clous : achetés 6 000, vendus 10 000
        $this->ciment = Produit::factory()->create(['nom' => 'Ciment', 'stock_actuel' => 0, 'prix_achat' => 30000, 'prix_vente' => 40000, 'stock_minimum' => 10, 'actif' => true]);
        $this->clous = Produit::factory()->create(['nom' => 'Clous', 'stock_actuel' => 0, 'prix_achat' => 6000, 'prix_vente' => 10000, 'stock_minimum' => 5, 'actif' => true]);
        app(MouvementStockService::class)->enregistrer($this->ciment, TypeMouvementStock::AjustementPositif, 100);
        app(MouvementStockService::class)->enregistrer($this->clous, TypeMouvementStock::AjustementPositif, 100);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function vendre(string $quand, array $lignes, ?Client $client = null, ModePaiement $mode = ModePaiement::Especes)
    {
        Carbon::setTestNow($quand);
        $vente = app(VenteService::class)->creer($client ?? $this->comptoir, $lignes, 0, $mode, $mode === ModePaiement::Credit ? 0 : 10000000);
        Carbon::setTestNow('2026-10-12 15:00:00');

        return $vente;
    }

    public function test_ventes_benefice_et_achats_du_jour_avec_retours_et_depenses(): void
    {
        // Aujourd'hui : 3 ciments + 2 clous = 140 000 (coût 102 000)
        $vente = $this->vendre('2026-10-12 09:00:00', [['produit_id' => $this->ciment->id, 'quantite' => 3], ['produit_id' => $this->clous->id, 'quantite' => 2]]);
        // Vente annulée : ignorée
        $annulee = $this->vendre('2026-10-12 10:00:00', [['produit_id' => $this->ciment->id, 'quantite' => 5]]);
        app(VenteService::class)->annuler($annulee, 'Erreur de caisse');
        // Hier : 1 ciment = 40 000 (coût 30 000)
        $this->vendre('2026-10-11 16:00:00', [['produit_id' => $this->ciment->id, 'quantite' => 1]]);

        // Retour d'un ciment aujourd'hui : −40 000 de ventes, −30 000 de coût
        app(RetourService::class)->creerClient($vente, [$this->ciment->id => 1], 'Produit défectueux', ModePaiement::Especes);

        // Dépenses du jour : 15 000 (la dépense supprimée et celle d'hier ne comptent pas)
        Depense::factory()->create(['categorie' => CategorieDepense::Transport, 'montant' => 15000, 'date_depense' => '2026-10-12']);
        Depense::factory()->create(['montant' => 99000, 'date_depense' => '2026-10-12'])->delete();
        Depense::factory()->create(['montant' => 5000, 'date_depense' => '2026-10-11']);

        // Achats du jour : 300 000 (l'achat annulé est exclu)
        $fournisseur = Fournisseur::factory()->create(['actif' => true]);
        app(AchatService::class)->creer($fournisseur, [['produit_id' => $this->clous->id, 'quantite' => 50, 'prix_achat' => 6000]], 300000, ModePaiement::Especes, null, today());
        $annule = app(AchatService::class)->creer($fournisseur, [['produit_id' => $this->clous->id, 'quantite' => 10, 'prix_achat' => 6000]], 0, null, null, today());
        app(AchatService::class)->annuler($annule, 'Erreur de saisie');

        $chiffres = $this->tableau->chiffresDuJour();

        $this->assertEquals(100000, $chiffres['ventes']['valeur'], '140 000 vendus − 40 000 retournés.');
        $this->assertEquals(40000, $chiffres['ventes']['precedent']);
        $this->assertEquals(150.0, $chiffres['ventes']['tendance']);
        $this->assertSame(1, $chiffres['nombre_ventes']);

        // Bénéfice = 100 000 − (102 000 − 30 000) − 15 000 = 13 000 ; hier = 40 000 − 30 000 − 5 000 = 5 000
        $this->assertEquals(13000, $chiffres['benefice']['valeur']);
        $this->assertEquals(5000, $chiffres['benefice']['precedent']);
        $this->assertEquals(160.0, $chiffres['benefice']['tendance']);
        $this->assertEquals(15000, $chiffres['depenses']);

        $this->assertEquals(300000, $chiffres['achats']['valeur']);
        $this->assertNull($chiffres['achats']['tendance'], 'Pas d\'achat hier : pas de tendance.');

        $this->assertCount(7, $chiffres['serie']['ventes']);
        $this->assertEquals([0, 0, 0, 0, 0, 40000, 100000], $chiffres['serie']['ventes']);
    }

    public function test_tendance(): void
    {
        $this->assertEquals(50.0, $this->tableau->tendance(150, 100));
        $this->assertEquals(-25.0, $this->tableau->tendance(75, 100));
        $this->assertEquals(200.0, $this->tableau->tendance(100, -100), 'Bénéfice négatif la veille : variation sur la valeur absolue.');
        $this->assertNull($this->tableau->tendance(100, 0));
    }

    public function test_serie_des_ventes_7_30_90_jours(): void
    {
        $this->vendre('2026-10-12 09:00:00', [['produit_id' => $this->clous->id, 'quantite' => 1]]);   // 10 000
        $this->vendre('2026-10-06 09:00:00', [['produit_id' => $this->clous->id, 'quantite' => 2]]);   // 20 000 (7 jours : bord inclus)
        $this->vendre('2026-10-05 09:00:00', [['produit_id' => $this->clous->id, 'quantite' => 3]]);   // 30 000 (période précédente des 7 jours)
        $this->vendre('2026-07-20 09:00:00', [['produit_id' => $this->clous->id, 'quantite' => 4]]);   // 40 000 (dans les 90 jours)

        $sept = $this->tableau->serieVentes(7);
        $this->assertCount(7, $sept['valeurs']);
        $this->assertEquals([20000, 0, 0, 0, 0, 0, 10000], $sept['valeurs']);
        $this->assertEquals(30000, $sept['total']);
        $this->assertEquals(30000, $sept['precedent']);
        $this->assertEquals(0.0, $sept['tendance']);

        $trente = $this->tableau->serieVentes(30);
        $this->assertCount(30, $trente['valeurs']);
        $this->assertEquals(60000, $trente['total']);

        $quatreVingtDix = $this->tableau->serieVentes(90);
        $this->assertLessThanOrEqual(14, count($quatreVingtDix['valeurs']), 'Regroupé par semaine.');
        $this->assertEquals(100000, $quatreVingtDix['total']);
        $this->assertEquals(100000, array_sum($quatreVingtDix['valeurs']));
        $this->assertStringStartsWith('Sem. du ', $quatreVingtDix['libelles'][0]);

        $this->assertSame(30, $this->tableau->serieVentes(12)['jours'], 'Période inconnue : 30 jours.');
    }

    public function test_stock_faible_top_produits_creances_et_compteurs(): void
    {
        // Stock faible : ciment 8/10 (80 %), clous 1/5 (20 %), produit inactif ignoré
        app(MouvementStockService::class)->enregistrer($this->ciment, TypeMouvementStock::Perte, 92);
        app(MouvementStockService::class)->enregistrer($this->clous, TypeMouvementStock::Perte, 99);
        Produit::factory()->create(['nom' => 'Inactif', 'actif' => false, 'stock_actuel' => 0, 'stock_minimum' => 10]);
        $faible = $this->tableau->stockFaible();
        $this->assertSame(['Clous', 'Ciment'], $faible->pluck('nom')->all());
        $this->assertSame([20, 80], $faible->pluck('remplissage')->all());

        // Top du mois : le mois précédent est exclu
        $client = Client::factory()->create(['nom' => 'Rakoto BTP', 'plafond_credit' => 1000000, 'actif' => true]);
        app(MouvementStockService::class)->enregistrer($this->ciment, TypeMouvementStock::AjustementPositif, 50);
        app(MouvementStockService::class)->enregistrer($this->clous, TypeMouvementStock::AjustementPositif, 50);
        $this->vendre('2026-10-03 09:00:00', [['produit_id' => $this->clous->id, 'quantite' => 3]]);
        $this->vendre('2026-10-04 09:00:00', [['produit_id' => $this->ciment->id, 'quantite' => 2]], $client, ModePaiement::Credit);
        $this->vendre('2026-09-20 09:00:00', [['produit_id' => $this->clous->id, 'quantite' => 40]]);
        $top = $this->tableau->topProduits();
        $this->assertSame(['Ciment', 'Clous'], $top->pluck('nom')->all());
        $this->assertEquals([80000, 30000], $top->pluck('chiffre')->all());
        $this->assertEquals([100, 38], $top->pluck('part')->all());
        $this->assertEquals(3, $top[1]->quantite);

        // Créances : la vente à crédit de Rakoto BTP
        $creances = $this->tableau->creances();
        $this->assertEquals(80000, $creances['total']);
        $this->assertSame(1, $creances['debiteurs']);
        $this->assertSame('Rakoto BTP', $creances['principaux']->first()->nom);
        $this->assertEquals(0, $creances['plus_de_60']);

        $compteurs = $this->tableau->compteurs();
        $this->assertSame(2, $compteurs['produits']['total']);
        $this->assertSame(1, $compteurs['clients']['total'], 'Client comptoir exclu.');
    }

    public function test_cache_de_60_secondes(): void
    {
        $this->vendre('2026-10-12 09:00:00', [['produit_id' => $this->clous->id, 'quantite' => 1]]);
        $this->assertEquals(10000, $this->tableau->chiffresDuJour()['ventes']['valeur']);

        // Nouvelle vente : invisible pendant 60 s (aucune requête SQL, valeur en cache)
        $this->vendre('2026-10-12 15:00:00', [['produit_id' => $this->clous->id, 'quantite' => 1]]);
        DB::enableQueryLog();
        $this->assertEquals(10000, $this->tableau->chiffresDuJour()['ventes']['valeur']);
        $this->assertSame([], array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'ventes')));

        Carbon::setTestNow('2026-10-12 15:01:01');
        $this->assertEquals(20000, $this->tableau->chiffresDuJour()['ventes']['valeur'], 'Recalculé après 60 s.');
    }
}
