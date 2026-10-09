<?php

namespace Tests\Feature\Achats;

use App\Enums\ModePaiement;
use App\Enums\StatutAchat;
use App\Enums\StatutPaiement;
use App\Enums\TypeMouvementStock;
use App\Exceptions\OperationRefuseeException;
use App\Models\Achat;
use App\Models\Fournisseur;
use App\Models\JournalActivite;
use App\Models\MouvementStock;
use App\Models\Parametre;
use App\Models\Produit;
use App\Models\Utilisateur;
use App\Services\AchatService;
use App\Services\MouvementStockService;
use App\Services\PaiementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AchatServiceTest extends TestCase
{
    use RefreshDatabase;

    private AchatService $service;

    private Fournisseur $fournisseur;

    private Produit $ciment;

    private Produit $tole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(Utilisateur::factory()->create());
        $this->service = app(AchatService::class);
        $this->fournisseur = Fournisseur::factory()->create(['nom' => 'Ravinala Matériaux']);
        $this->ciment = Produit::factory()->create(['nom' => 'Ciment 50 kg', 'stock_actuel' => 0, 'prix_achat' => 33000]);
        $this->tole = Produit::factory()->create(['nom' => 'Tôle 3 m', 'stock_actuel' => 0, 'prix_achat' => 28000]);
    }

    private function acheter(?array $lignes = null, float $paye = 0, ?ModePaiement $mode = null, bool $majPrix = true): Achat
    {
        return $this->service->creer(
            $this->fournisseur,
            $lignes ?? [
                ['produit_id' => $this->ciment->id, 'quantite' => 50, 'prix_achat' => 33000],
                ['produit_id' => $this->tole->id, 'quantite' => 20, 'prix_achat' => 27500],
            ],
            $paye,
            $mode,
            null,
            today(),
            $majPrix,
        );
    }

    public function test_numerotation_sequentielle_et_remise_a_zero_annuelle(): void
    {
        Carbon::setTestNow('2026-12-31 10:00:00');
        $this->assertSame('ACH-2026-00001', $this->acheter()->numero);
        $this->assertSame('ACH-2026-00002', $this->acheter()->numero);

        Carbon::setTestNow('2027-01-02 09:00:00');
        $this->assertSame('ACH-2027-00001', $this->acheter()->numero);

        Carbon::setTestNow();
    }

    public function test_le_stock_augmente_par_un_mouvement_achat_par_ligne(): void
    {
        $achat = $this->acheter();

        $this->assertEquals(50, $this->ciment->fresh()->stock_actuel);
        $this->assertEquals(20, $this->tole->fresh()->stock_actuel);

        $mouvements = MouvementStock::where('reference_type', 'achat')->where('reference_id', $achat->id)->get();
        $this->assertCount(2, $mouvements);
        $this->assertTrue($mouvements->every(fn ($m) => $m->type === TypeMouvementStock::Achat));
        $this->assertTrue(app(MouvementStockService::class)->verifierCoherence()->isEmpty());
    }

    public function test_total_calcule_cote_serveur_et_lignes_fusionnees(): void
    {
        $achat = $this->acheter([
            ['produit_id' => $this->ciment->id, 'quantite' => 10, 'prix_achat' => 33000],
            ['produit_id' => $this->ciment->id, 'quantite' => 5, 'prix_achat' => 33000],
            ['produit_id' => $this->ciment->id, 'quantite' => 2, 'prix_achat' => 30000], // autre prix : ligne distincte
        ]);

        $this->assertEquals(15 * 33000 + 2 * 30000, $achat->total);
        $this->assertCount(2, $achat->lignes);
        $this->assertEquals(17, $this->ciment->fresh()->stock_actuel);
    }

    public function test_mise_a_jour_du_prix_d_achat_selon_l_option(): void
    {
        $this->acheter();
        $this->assertEquals(27500, $this->tole->fresh()->prix_achat);

        $this->acheter([['produit_id' => $this->tole->id, 'quantite' => 1, 'prix_achat' => 26000]], majPrix: false);
        $this->assertEquals(27500, $this->tole->fresh()->prix_achat);
    }

    public function test_achat_paye_partiel_et_a_credit(): void
    {
        $total = 50 * 33000 + 20 * 27500;

        $paye = $this->acheter(paye: $total, mode: ModePaiement::Virement);
        $this->assertSame(StatutPaiement::Paye, $paye->statut_paiement);
        $this->assertCount(1, $paye->paiements);
        $this->assertEquals(0, $paye->reste_a_payer);

        $partiel = $this->acheter(paye: 1000000, mode: ModePaiement::Especes);
        $this->assertSame(StatutPaiement::Partiel, $partiel->statut_paiement);
        $this->assertEquals($total - 1000000, $partiel->reste_a_payer);

        $credit = $this->acheter();
        $this->assertSame(StatutPaiement::Credit, $credit->statut_paiement);
        $this->assertCount(0, $credit->paiements);
        $this->assertEquals($total, $credit->reste_a_payer);

        $this->assertEquals(($total - 1000000) + $total, $this->fournisseur->fresh()->dette_totale);
    }

    public function test_refus_metier(): void
    {
        $refus = function (callable $action, string $message) {
            try {
                $action();
                $this->fail("Refus attendu : {$message}");
            } catch (OperationRefuseeException $e) {
                $this->assertStringContainsString($message, $e->getMessage());
            }
        };

        $refus(fn () => $this->acheter(paye: 99999999, mode: ModePaiement::Especes), 'compris entre 0 et le total');
        $refus(fn () => $this->acheter(paye: 1000), 'mode de paiement');
        $refus(fn () => $this->acheter([]), 'au moins un produit');

        $this->tole->update(['actif' => false]);
        $refus(fn () => $this->acheter(), 'désactivé');

        $this->fournisseur->update(['actif' => false]);
        $refus(fn () => $this->acheter([['produit_id' => $this->ciment->id, 'quantite' => 1, 'prix_achat' => 1]]), 'désactivé');

        $this->assertSame(0, Achat::count());
        $this->assertEquals(0, $this->ciment->fresh()->stock_actuel);
    }

    public function test_atomicite_rien_n_est_cree_si_l_enregistrement_echoue(): void
    {
        // Le paiement échoue en fin de transaction (mode crédit interdit) : tout est annulé
        try {
            $this->acheter(paye: 1000, mode: ModePaiement::Credit);
        } catch (OperationRefuseeException) {
        }

        $this->assertSame(0, Achat::count());
        $this->assertSame(0, MouvementStock::count());
        $this->assertEquals(0, $this->ciment->fresh()->stock_actuel);
        $this->assertSame('ACH-'.now()->year.'-00001', $this->acheter()->numero, 'Aucun numéro consommé.');
    }

    public function test_annulation_retire_le_stock_et_journalise(): void
    {
        $achat = $this->acheter(paye: 500000, mode: ModePaiement::Especes);

        $this->service->annuler($achat, 'Erreur de saisie');

        $this->assertSame(StatutAchat::Annule, $achat->fresh()->statut);
        $this->assertEquals(0, $this->ciment->fresh()->stock_actuel);
        $this->assertEquals(0, $this->tole->fresh()->stock_actuel);
        $this->assertSame(2, MouvementStock::where('reference_id', $achat->id)->where('type', 'ajustement_negatif')->count());
        $this->assertDatabaseHas('journal_activites', ['action' => 'achat.annule', 'modele' => 'achat', 'modele_id' => $achat->id]);
        $this->assertSame('Erreur de saisie', JournalActivite::where('action', 'achat.annule')->first()->details['motif']);
        $this->assertTrue(app(MouvementStockService::class)->verifierCoherence()->isEmpty());

        // Un achat annulé ne compte plus dans la dette
        $this->assertEquals(0, $this->fournisseur->fresh()->dette_totale);
    }

    public function test_annulation_refusee_si_le_stock_ne_suffit_plus(): void
    {
        $achat = $this->acheter();
        // 45 sacs de ciment ont été vendus entre-temps
        app(MouvementStockService::class)->enregistrer($this->ciment, TypeMouvementStock::Vente, 45);
        Parametre::create(['cle' => 'stock_negatif_autorise', 'valeur' => '1']); // même ainsi : refus

        try {
            $this->service->annuler($achat, 'Retour fournisseur');
            $this->fail('Annulation refusée attendue.');
        } catch (OperationRefuseeException $e) {
            $this->assertSame('Annulation impossible : stock insuffisant pour « Ciment 50 kg » (5 disponible(s), 50 à retirer).', $e->getMessage());
        }

        $this->assertSame(StatutAchat::Valide, $achat->fresh()->statut);
        $this->assertEquals(5, $this->ciment->fresh()->stock_actuel);
        $this->assertEquals(20, $this->tole->fresh()->stock_actuel);
    }

    public function test_double_annulation_et_motif_vide_refuses(): void
    {
        $achat = $this->acheter();

        try {
            $this->service->annuler($achat, '   ');
            $this->fail('Motif vide refusé attendu.');
        } catch (OperationRefuseeException $e) {
            $this->assertSame('Indiquez le motif de l\'annulation.', $e->getMessage());
        }

        $this->service->annuler($achat, 'Première annulation');

        try {
            $this->service->annuler($achat, 'Seconde annulation');
            $this->fail('Double annulation refusée attendue.');
        } catch (OperationRefuseeException $e) {
            $this->assertStringContainsString('est déjà annulé', $e->getMessage());
        }

        // Les quantités n'ont été retirées qu'une fois
        $this->assertSame(2, MouvementStock::where('reference_id', $achat->id)->where('type', 'ajustement_negatif')->count());
    }

    public function test_paiements_ulterieurs_jusqu_au_solde(): void
    {
        $achat = $this->acheter([['produit_id' => $this->ciment->id, 'quantite' => 10, 'prix_achat' => 33000]]);
        $paiements = app(PaiementService::class);

        $paiements->enregistrer($achat, 100000, ModePaiement::Especes);
        $this->assertEquals(230000, $achat->fresh()->reste_a_payer);
        $this->assertSame(StatutPaiement::Partiel, $achat->fresh()->statut_paiement);

        try {
            $paiements->enregistrer($achat, 300000, ModePaiement::Especes);
            $this->fail('Dépassement refusé attendu.');
        } catch (OperationRefuseeException $e) {
            $this->assertStringContainsString('dépasse le reste à payer', $e->getMessage());
        }

        $paiements->enregistrer($achat, 230000, ModePaiement::MobileMoney, 'MVOLA-123');
        $this->assertSame(StatutPaiement::Paye, $achat->fresh()->statut_paiement);
        $this->assertEquals(330000, $achat->fresh()->montant_paye);
        $this->assertCount(2, $achat->fresh()->paiements);
    }

    public function test_paiement_refuse_sur_un_achat_annule(): void
    {
        $achat = $this->acheter();
        $this->service->annuler($achat, 'Erreur de saisie');

        $this->expectException(OperationRefuseeException::class);
        $this->expectExceptionMessage('est annulé');

        app(PaiementService::class)->enregistrer($achat, 1000, ModePaiement::Especes);
    }
}
