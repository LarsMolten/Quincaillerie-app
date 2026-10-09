<?php

namespace Tests\Feature\Ventes;

use App\Enums\ModePaiement;
use App\Enums\StatutFacture;
use App\Enums\StatutPaiement;
use App\Enums\StatutVente;
use App\Enums\TypeMouvementStock;
use App\Exceptions\OperationRefuseeException;
use App\Exceptions\StockInsuffisantException;
use App\Models\Client;
use App\Models\Facture;
use App\Models\JournalActivite;
use App\Models\MouvementStock;
use App\Models\Parametre;
use App\Models\Produit;
use App\Models\Role;
use App\Models\Utilisateur;
use App\Models\Vente;
use App\Services\MouvementStockService;
use App\Services\VenteService;
use Database\Seeders\ClientComptoirSeeder;
use Database\Seeders\DroitSeeder;
use Database\Seeders\ParametreSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VenteServiceTest extends TestCase
{
    use RefreshDatabase;

    private VenteService $service;

    private Client $comptoir;

    private Client $client;

    private Produit $ciment;

    private Produit $clous;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, DroitSeeder::class, ParametreSeeder::class, ClientComptoirSeeder::class]);
        $this->connecter(RoleSeeder::RESPONSABLE);
        $this->service = app(VenteService::class);
        $this->comptoir = Client::where('nom', Client::COMPTOIR)->firstOrFail();
        $this->client = Client::factory()->create(['nom' => 'Rakoto BTP', 'plafond_credit' => 500000]);

        // Stock initial par un vrai mouvement (jamais d'écriture directe)
        $this->ciment = Produit::factory()->create(['nom' => 'Ciment 50 kg', 'stock_actuel' => 0, 'prix_achat' => 33000, 'prix_vente' => 38000, 'prix_gros' => 36000]);
        $this->clous = Produit::factory()->create(['nom' => 'Clous 70 mm (kg)', 'stock_actuel' => 0, 'prix_achat' => 6000, 'prix_vente' => 8000, 'prix_gros' => null]);
        app(MouvementStockService::class)->enregistrer($this->ciment, TypeMouvementStock::Achat, 100);
        app(MouvementStockService::class)->enregistrer($this->clous, TypeMouvementStock::Achat, 20);
    }

    private function connecter(string $role): Utilisateur
    {
        $utilisateur = Utilisateur::factory()->create(['role_id' => Role::where('nom', $role)->firstOrFail()->id]);
        $this->actingAs($utilisateur);

        return $utilisateur;
    }

    private function vendre(
        ?array $lignes = null,
        ModePaiement $mode = ModePaiement::Especes,
        ?float $recu = null,
        ?Client $client = null,
        float $remise = 0,
    ): Vente {
        $lignes ??= [['produit_id' => $this->ciment->id, 'quantite' => 2], ['produit_id' => $this->clous->id, 'quantite' => 1.5]];

        return $this->service->creer($client ?? $this->comptoir, $lignes, $remise, $mode, $recu ?? 1000000);
    }

    private function refus(callable $action, string $message): void
    {
        try {
            $action();
            $this->fail("Refus attendu : {$message}");
        } catch (OperationRefuseeException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }

    public function test_numero_vente_et_facture_emise(): void
    {
        $vente = $this->vendre();

        $this->assertSame('VTE-'.now()->year.'-00001', $vente->numero);
        $this->assertSame('FAC-'.now()->year.'-00001', $vente->facture->numero);
        $this->assertSame(StatutFacture::Emise, $vente->facture->statut);
        $this->assertEquals($vente->total, $vente->facture->total);
        $this->assertSame('VTE-'.now()->year.'-00002', $this->vendre()->numero);
    }

    public function test_le_stock_diminue_par_un_mouvement_vente_par_ligne(): void
    {
        $vente = $this->vendre();

        $this->assertEquals(98, $this->ciment->fresh()->stock_actuel);
        $this->assertEquals(18.5, $this->clous->fresh()->stock_actuel);
        $mouvements = MouvementStock::where('reference_type', 'vente')->where('reference_id', $vente->id)->get();
        $this->assertCount(2, $mouvements);
        $this->assertTrue($mouvements->every(fn ($m) => $m->type === TypeMouvementStock::Vente));
        $this->assertTrue(app(MouvementStockService::class)->verifierCoherence()->isEmpty());
    }

    public function test_prix_serveur_cout_d_achat_fige_et_tarif_gros(): void
    {
        $vente = $this->vendre([
            ['produit_id' => $this->ciment->id, 'quantite' => 10, 'tarif' => 'gros', 'prix_unitaire' => 1], // prix navigateur ignoré
            ['produit_id' => $this->ciment->id, 'quantite' => 1],
        ]);

        $gros = $vente->lignes->firstWhere('prix_unitaire', 36000);
        $this->assertNotNull($gros);
        $this->assertEquals(10, $gros->quantite);
        $this->assertEquals(33000, $gros->prix_achat_unitaire);
        $this->assertEquals(10 * 36000 + 38000, $vente->total);

        // Le coût reste celui du jour de la vente
        $this->ciment->update(['prix_achat' => 40000]);
        $this->assertEquals(33000, $gros->fresh()->prix_achat_unitaire);

        $this->refus(fn () => $this->vendre([['produit_id' => $this->clous->id, 'quantite' => 1, 'tarif' => 'gros']]), 'pas de prix de gros');
    }

    public function test_vente_refusee_si_stock_insuffisant_sans_rien_creer(): void
    {
        try {
            $this->vendre([['produit_id' => $this->ciment->id, 'quantite' => 5], ['produit_id' => $this->clous->id, 'quantite' => 25]]);
            $this->fail('Refus attendu.');
        } catch (StockInsuffisantException $e) {
            $this->assertSame('Stock insuffisant pour « Clous 70 mm (kg) » : 20 disponible(s), 25 demandé(s).', $e->getMessage());
        }

        $this->assertSame(0, Vente::count());
        $this->assertSame(0, Facture::count());
        $this->assertEquals(100, $this->ciment->fresh()->stock_actuel, 'La première ligne est annulée aussi.');
        $this->assertSame('VTE-'.now()->year.'-00001', $this->vendre()->numero, 'Aucun numéro consommé.');
    }

    public function test_especes_avec_monnaie_rendue(): void
    {
        $vente = $this->vendre([['produit_id' => $this->ciment->id, 'quantite' => 2]], recu: 100000);

        $this->assertEquals(76000, $vente->total);
        $this->assertEquals(76000, $vente->montant_paye);
        $this->assertEquals(100000, $vente->montant_recu);
        $this->assertEquals(24000, $vente->monnaie_rendue);
        $this->assertSame(StatutPaiement::Paye, $vente->statut_paiement);
        $this->assertCount(1, $vente->paiements);

        $this->refus(fn () => $this->vendre([['produit_id' => $this->ciment->id, 'quantite' => 1]], ModePaiement::MobileMoney, 50000), 'ne peut pas dépasser le total');
    }

    public function test_credit_interdit_au_client_comptoir(): void
    {
        $this->refus(fn () => $this->vendre(mode: ModePaiement::Credit), 'interdit pour le Client comptoir');
        // Paiement partiel : le reste serait aussi un crédit
        $this->refus(fn () => $this->vendre([['produit_id' => $this->ciment->id, 'quantite' => 2]], recu: 50000), 'interdit pour le Client comptoir');
        $this->assertSame(0, Vente::count());
    }

    public function test_credit_selon_le_plafond_du_client(): void
    {
        // 10 sacs = 380 000 Ar ≤ plafond 500 000 : accepté
        $vente = $this->vendre([['produit_id' => $this->ciment->id, 'quantite' => 10]], ModePaiement::Credit, client: $this->client);
        $this->assertSame(StatutPaiement::Credit, $vente->statut_paiement);
        $this->assertEquals(380000, $vente->reste_a_payer);
        $this->assertNull($vente->montant_recu);
        $this->assertCount(0, $vente->paiements);
        $this->assertEquals(380000, $this->client->fresh()->creance_totale);

        // 380 000 + 152 000 > 500 000 : refusé
        $this->refus(
            fn () => $this->vendre([['produit_id' => $this->ciment->id, 'quantite' => 4]], ModePaiement::Credit, client: $this->client),
            'Plafond de crédit dépassé pour « Rakoto BTP »',
        );

        // Partiel : 100 000 payés, 52 000 de reste ⇒ 432 000 ≤ 500 000
        $partiel = $this->vendre([['produit_id' => $this->ciment->id, 'quantite' => 4]], ModePaiement::Especes, 100000, $this->client);
        $this->assertSame(StatutPaiement::Partiel, $partiel->statut_paiement);
        $this->assertEquals(52000, $partiel->reste_a_payer);
        $this->assertEquals(0, $partiel->monnaie_rendue);

        // Sans plafond : aucun crédit
        $sansPlafond = Client::factory()->create(['plafond_credit' => null]);
        $this->refus(fn () => $this->vendre(mode: ModePaiement::Credit, client: $sansPlafond), 'pas de plafond de crédit');
    }

    public function test_remise_refusee_sans_le_droit(): void
    {
        $this->connecter(RoleSeeder::VENDEUR);

        $this->refus(fn () => $this->vendre(remise: 1000), 'pas le droit d\'accorder une remise');
        $this->refus(fn () => $this->vendre([['produit_id' => $this->ciment->id, 'quantite' => 1, 'remise' => 500]]), 'pas le droit d\'accorder une remise');

        $this->assertSame(0, Vente::count());
        $this->assertNotNull($this->vendre(), 'Sans remise, le vendeur vend normalement.');
    }

    public function test_remise_plafonnee_et_journalisee(): void
    {
        // 10 sacs = 380 000 Ar ; plafond 10 % = 38 000 Ar
        $this->refus(fn () => $this->vendre([['produit_id' => $this->ciment->id, 'quantite' => 10, 'remise' => 20000]], remise: 20000), 'dépasse le plafond autorisé de 10 %');

        $vente = $this->vendre([['produit_id' => $this->ciment->id, 'quantite' => 10, 'remise' => 8000]], remise: 30000);

        $this->assertEquals(372000, $vente->sous_total);
        $this->assertEquals(30000, $vente->remise);
        $this->assertEquals(342000, $vente->total);
        $this->assertEquals(8000, $vente->lignes->first()->remise);
        $journal = JournalActivite::where('action', 'vente.remise')->firstOrFail();
        $this->assertEquals(10, $journal->details['pourcentage']);

        Parametre::where('cle', 'remise_max_pourcentage')->update(['valeur' => '0']);
        Parametre::viderCache();
        $this->refus(fn () => $this->vendre(remise: 1), 'plafond autorisé de 0 %');
    }

    public function test_annulation_remet_le_stock_annule_la_facture_et_journalise(): void
    {
        $vente = $this->vendre();

        $this->service->annuler($vente, 'Erreur de caisse');

        $this->assertSame(StatutVente::Annulee, $vente->fresh()->statut);
        $this->assertSame(StatutFacture::Annulee, $vente->facture->fresh()->statut);
        $this->assertEquals(100, $this->ciment->fresh()->stock_actuel);
        $this->assertEquals(20, $this->clous->fresh()->stock_actuel);
        $this->assertSame(2, MouvementStock::where('reference_type', 'vente')->where('type', 'ajustement_positif')->count());
        $this->assertSame('Erreur de caisse', JournalActivite::where('action', 'vente.annule')->firstOrFail()->details['motif']);
        $this->assertTrue(app(MouvementStockService::class)->verifierCoherence()->isEmpty());

        $this->refus(fn () => $this->service->annuler($vente, 'Encore'), 'déjà annulée');
        $this->refus(fn () => $this->service->annuler($this->vendre(), '  '), 'motif');
    }

    public function test_une_vente_a_credit_annulee_libere_le_plafond(): void
    {
        $vente = $this->vendre([['produit_id' => $this->ciment->id, 'quantite' => 13]], ModePaiement::Credit, client: $this->client);
        $this->assertEquals(494000, $this->client->fresh()->creance_totale);

        $this->service->annuler($vente, 'Client parti');

        $this->assertEquals(0, $this->client->fresh()->creance_totale);
        $this->assertNotNull($this->vendre([['produit_id' => $this->ciment->id, 'quantite' => 13]], ModePaiement::Credit, client: $this->client));
    }

    public function test_refus_divers(): void
    {
        $this->refus(fn () => $this->vendre([]), 'ticket est vide');
        $this->refus(fn () => $this->vendre(mode: ModePaiement::Especes, recu: 0), 'montant reçu');

        $this->clous->update(['actif' => false]);
        $this->refus(fn () => $this->vendre(), 'désactivé');

        $this->client->update(['actif' => false]);
        $this->refus(fn () => $this->vendre([['produit_id' => $this->ciment->id, 'quantite' => 1]], client: $this->client), 'désactivé');
    }
}
