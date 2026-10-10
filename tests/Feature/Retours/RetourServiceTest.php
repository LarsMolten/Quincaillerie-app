<?php

namespace Tests\Feature\Retours;

use App\Enums\ModePaiement;
use App\Enums\StatutRetour;
use App\Enums\TypeMouvementStock;
use App\Exceptions\OperationRefuseeException;
use App\Exceptions\StockInsuffisantException;
use App\Models\Achat;
use App\Models\Client;
use App\Models\Fournisseur;
use App\Models\MouvementStock;
use App\Models\Produit;
use App\Models\Retour;
use App\Models\Role;
use App\Models\Utilisateur;
use App\Models\Vente;
use App\Services\AchatService;
use App\Services\MouvementStockService;
use App\Services\PaiementService;
use App\Services\RetourService;
use App\Services\VenteService;
use Database\Seeders\ClientComptoirSeeder;
use Database\Seeders\DroitSeeder;
use Database\Seeders\ParametreSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RetourServiceTest extends TestCase
{
    use RefreshDatabase;

    private RetourService $service;

    private Produit $ciment;

    private Produit $clous;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-11 09:00:00');
        $this->seed([RoleSeeder::class, DroitSeeder::class, ParametreSeeder::class, ClientComptoirSeeder::class]);
        $this->actingAs(Utilisateur::factory()->create(['role_id' => Role::where('nom', RoleSeeder::RESPONSABLE)->firstOrFail()->id]));
        $this->service = app(RetourService::class);
        $this->client = Client::factory()->create(['nom' => 'Rakoto BTP', 'plafond_credit' => 1000000]);

        $this->ciment = Produit::factory()->create(['nom' => 'Ciment 50 kg', 'stock_actuel' => 0, 'prix_achat' => 33000, 'prix_vente' => 40000]);
        $this->clous = Produit::factory()->create(['nom' => 'Clous 70 mm (kg)', 'stock_actuel' => 0, 'prix_achat' => 6000, 'prix_vente' => 10000]);
        app(MouvementStockService::class)->enregistrer($this->ciment, TypeMouvementStock::Achat, 100);
        app(MouvementStockService::class)->enregistrer($this->clous, TypeMouvementStock::Achat, 20);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function vendre(ModePaiement $mode = ModePaiement::Especes, float $remise = 0, ?Client $client = null): Vente
    {
        return app(VenteService::class)->creer(
            $client ?? Client::where('nom', Client::COMPTOIR)->firstOrFail(),
            [['produit_id' => $this->ciment->id, 'quantite' => 5], ['produit_id' => $this->clous->id, 'quantite' => 2]],
            $remise,
            $mode,
            $mode === ModePaiement::Credit ? 0 : 1000000,
        );
    }

    private function refus(callable $action, string $message): void
    {
        try {
            $action();
            $this->fail("Refus attendu : {$message}");
        } catch (OperationRefuseeException|StockInsuffisantException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }

    public function test_retour_client_remet_en_stock_et_rembourse(): void
    {
        $vente = $this->vendre(); // 5 ciments + 2 kg de clous, payé comptant : 220 000 Ar
        $this->assertEquals(95, $this->ciment->fresh()->stock_actuel);

        $retour = $this->service->creerClient($vente, [$this->ciment->id => 2], 'Produit défectueux', ModePaiement::Especes);

        $this->assertSame('RET-2026-00001', $retour->numero);
        $this->assertEquals(97, $this->ciment->fresh()->stock_actuel);
        $this->assertEquals(80000, $retour->total);
        $this->assertEquals(0, $retour->montant_avoir, 'Vente soldée : rien à imputer.');
        $this->assertEquals(80000, $retour->montant_rembourse);
        $this->assertSame(ModePaiement::Especes, $retour->mode_remboursement);

        $mouvement = MouvementStock::where('reference_type', 'retour')->where('reference_id', $retour->id)->sole();
        $this->assertSame(TypeMouvementStock::RetourClient, $mouvement->type);
        $this->assertDatabaseHas('journal_activites', ['action' => 'retour.cree', 'modele' => 'retour', 'modele_id' => $retour->id]);
    }

    public function test_quantites_limitees_au_vendu_moins_les_retours_deja_faits(): void
    {
        $vente = $this->vendre();

        $this->service->creerClient($vente, [$this->ciment->id => 3], 'Erreur de quantité', ModePaiement::Especes);
        $this->refus(fn () => $this->service->creerClient($vente, [$this->ciment->id => 3], 'Erreur de quantité', ModePaiement::Especes),
            'Retour impossible pour « Ciment 50 kg » : 2 retournable(s) sur 5, 3 demandé(s).');
        $this->service->creerClient($vente, [$this->ciment->id => 2], 'Erreur de quantité', ModePaiement::Especes);

        $this->assertEquals(0, $this->service->retournables($vente->fresh())[$this->ciment->id]['retournable']);
        $this->refus(fn () => $this->service->creerClient($vente, [9999 => 1], 'Erreur', ModePaiement::Especes), 'ne figure pas');
        $this->refus(fn () => $this->service->creerClient($vente, [$this->ciment->id => 0], 'Erreur', ModePaiement::Especes), 'au moins un produit');
        $this->refus(fn () => $this->service->creerClient($vente, [$this->clous->id => 1], '  ', ModePaiement::Especes), 'motif');
    }

    public function test_prix_au_prorata_de_la_remise_globale(): void
    {
        // 5 × 40 000 + 2 × 10 000 = 220 000 ; remise globale 22 000 (10 %) => total 198 000
        $vente = $this->vendre(ModePaiement::Especes, 22000);

        $retour = $this->service->creerClient($vente, [$this->ciment->id => 1], 'Produit défectueux', ModePaiement::Especes);

        $this->assertEquals(36000, $retour->lignes->first()->prix_unitaire);
        $this->assertEquals(36000, $retour->total);
    }

    public function test_avoir_sur_la_creance_puis_remboursement_de_l_excedent(): void
    {
        // Vente à crédit de 220 000 : rien payé
        $vente = $this->vendre(ModePaiement::Credit, 0, $this->client);
        $this->assertEquals(220000, $this->client->creance_totale);

        $retour = $this->service->creerClient($vente, [$this->ciment->id => 2], 'Client a changé d\'avis');
        $this->assertEquals(80000, $retour->montant_avoir);
        $this->assertEquals(0, $retour->montant_rembourse);
        $this->assertNull($retour->mode_remboursement);
        $this->assertEquals(140000, $vente->fresh()->reste_a_payer);
        $this->assertEquals(140000, $this->client->fresh()->creance_totale);

        // Le client a payé 100 000 entre-temps : reste 40 000 ; retour de 120 000 => 40 000 d'avoir, 80 000 remboursés
        app(PaiementService::class)->enregistrer($vente, 100000, ModePaiement::Especes);
        $this->refus(fn () => $this->service->creerClient($vente, [$this->ciment->id => 3], 'Erreur de produit'), 'Choisissez le mode de remboursement des 80');

        $retour = $this->service->creerClient($vente, [$this->ciment->id => 3], 'Erreur de produit', ModePaiement::MobileMoney);
        $this->assertEquals(40000, $retour->montant_avoir);
        $this->assertEquals(80000, $retour->montant_rembourse);
        $this->assertEquals(0, $vente->fresh()->reste_a_payer);
    }

    public function test_retour_fournisseur_sortie_de_stock_et_dette_diminuee(): void
    {
        $fournisseur = Fournisseur::factory()->create(['actif' => true]);
        // 10 sacs à 33 000 = 330 000, 100 000 payés => dette 230 000
        $achat = app(AchatService::class)->creer($fournisseur, [['produit_id' => $this->ciment->id, 'quantite' => 10, 'prix_achat' => 33000]], 100000, ModePaiement::Especes);
        $this->assertEquals(110, $this->ciment->fresh()->stock_actuel);

        $retour = $this->service->creerFournisseur($achat, [$this->ciment->id => 4], 'Non conforme à la commande');

        $this->assertEquals(106, $this->ciment->fresh()->stock_actuel);
        $this->assertEquals(132000, $retour->total);
        $this->assertEquals(132000, $retour->montant_avoir);
        $this->assertEquals(98000, $achat->fresh()->reste_a_payer);
        $this->assertSame(TypeMouvementStock::RetourFournisseur, MouvementStock::where('reference_type', 'retour')->sole()->type);
        $this->assertEquals(98000, $fournisseur->fresh()->dette_totale);
    }

    public function test_retour_fournisseur_refuse_si_stock_insuffisant(): void
    {
        $fournisseur = Fournisseur::factory()->create(['actif' => true]);
        $achat = app(AchatService::class)->creer($fournisseur, [['produit_id' => $this->clous->id, 'quantite' => 5, 'prix_achat' => 6000]], 30000, ModePaiement::Especes);
        // 25 kg en stock, dont 23 vendus : il n'en reste que 2
        app(VenteService::class)->creer(Client::where('nom', Client::COMPTOIR)->firstOrFail(), [['produit_id' => $this->clous->id, 'quantite' => 23]], 0, ModePaiement::Especes, 1000000);

        $this->refus(fn () => $this->service->creerFournisseur($achat, [$this->clous->id => 5], 'Produit défectueux', ModePaiement::Especes), 'Stock insuffisant');
        $this->assertSame(0, Retour::count(), 'Rien n\'est enregistré.');
    }

    public function test_numero_ret_sans_trou_et_remis_a_zero_chaque_annee(): void
    {
        $vente = $this->vendre();
        $achat = app(AchatService::class)->creer(Fournisseur::factory()->create(['actif' => true]), [['produit_id' => $this->ciment->id, 'quantite' => 5, 'prix_achat' => 33000]], 165000, ModePaiement::Especes);

        $numeros = [
            $this->service->creerClient($vente, [$this->ciment->id => 1], 'Erreur', ModePaiement::Especes)->numero,
            $this->service->creerFournisseur($achat, [$this->ciment->id => 1], 'Erreur', ModePaiement::Especes)->numero,
        ];
        // Échec : aucun numéro consommé
        $this->refus(fn () => $this->service->creerClient($vente, [$this->ciment->id => 50], 'Erreur', ModePaiement::Especes), 'Retour impossible');
        $numeros[] = $this->service->creerClient($vente, [$this->ciment->id => 1], 'Erreur', ModePaiement::Especes)->numero;
        $this->assertSame(['RET-2026-00001', 'RET-2026-00002', 'RET-2026-00003'], $numeros);

        Carbon::setTestNow('2027-01-03 10:00:00');
        $this->assertSame('RET-2027-00001', $this->service->creerClient($vente, [$this->ciment->id => 1], 'Erreur', ModePaiement::Especes)->numero);
    }

    public function test_annulation_retablit_stock_et_reste(): void
    {
        $vente = $this->vendre(ModePaiement::Credit, 0, $this->client);
        $retour = $this->service->creerClient($vente, [$this->ciment->id => 2, $this->clous->id => 1], 'Erreur de produit');
        $this->assertEquals(97, $this->ciment->fresh()->stock_actuel);
        $this->assertEquals(130000, $vente->fresh()->reste_a_payer);

        $this->refus(fn () => $this->service->annuler($retour, ' '), 'motif');
        $this->service->annuler($retour, 'Retour saisi par erreur');

        $this->assertSame(StatutRetour::Annule, $retour->fresh()->statut);
        $this->assertSame('RET-2026-00001', $retour->fresh()->numero);
        $this->assertEquals(95, $this->ciment->fresh()->stock_actuel);
        $this->assertEquals(18, $this->clous->fresh()->stock_actuel);
        $this->assertEquals(220000, $vente->fresh()->reste_a_payer);
        $this->assertDatabaseHas('journal_activites', ['action' => 'retour.annule', 'modele_id' => $retour->id]);
        $this->assertEquals(5, $this->service->retournables($vente->fresh())[$this->ciment->id]['retournable'], 'Un retour annulé ne compte plus.');

        $this->refus(fn () => $this->service->annuler($retour->fresh(), 'Encore'), 'déjà annulé');
    }

    public function test_annulation_de_la_vente_ou_de_l_achat_bloquee_par_un_retour_valide(): void
    {
        $vente = $this->vendre();
        $retour = $this->service->creerClient($vente, [$this->ciment->id => 1], 'Erreur', ModePaiement::Especes);
        $this->refus(fn () => app(VenteService::class)->annuler($vente, 'Erreur de caisse'), "annulez d'abord le retour {$retour->numero}");

        $this->service->annuler($retour, 'Saisie en double');
        app(VenteService::class)->annuler($vente->fresh(), 'Erreur de caisse');
        $this->refus(fn () => $this->service->creerClient($vente->fresh(), [$this->ciment->id => 1], 'Erreur', ModePaiement::Especes), 'est annulé');

        $achat = app(AchatService::class)->creer(Fournisseur::factory()->create(['actif' => true]), [['produit_id' => $this->ciment->id, 'quantite' => 5, 'prix_achat' => 33000]], 165000, ModePaiement::Especes);
        $retourFournisseur = $this->service->creerFournisseur($achat, [$this->ciment->id => 1], 'Erreur', ModePaiement::Especes);
        $this->refus(fn () => app(AchatService::class)->annuler($achat, 'Erreur de saisie'), "annulez d'abord le retour {$retourFournisseur->numero}");
        $this->assertInstanceOf(Achat::class, $achat);
    }

    public function test_stock_coherent_apres_retours_et_annulations(): void
    {
        $vente = $this->vendre();
        $retour = $this->service->creerClient($vente, [$this->ciment->id => 2], 'Erreur', ModePaiement::Especes);
        $this->service->annuler($retour, 'Erreur de saisie');
        $this->service->creerClient($vente, [$this->clous->id => 2], 'Erreur', ModePaiement::Especes);

        $this->assertSame([], app(MouvementStockService::class)->verifierCoherence()->all());
    }
}
