<?php

namespace Tests\Feature\Paiements;

use App\Enums\ModePaiement;
use App\Enums\StatutPaiement;
use App\Exceptions\OperationRefuseeException;
use App\Models\Achat;
use App\Models\Paiement;
use App\Models\Utilisateur;
use App\Models\Vente;
use App\Services\PaiementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class PaiementServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaiementService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-10 09:30:00');
        $this->actingAs(Utilisateur::factory()->create());
        $this->service = app(PaiementService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_paiement_partiel_puis_total_d_une_vente(): void
    {
        $vente = Vente::factory()->create(['total' => 100000, 'montant_paye' => 0, 'reste_a_payer' => 100000]);

        $premier = $this->service->enregistrer($vente, 40000, ModePaiement::Especes);
        $this->assertEquals(40000, $vente->montant_paye, 'L\'instance reçue reflète les nouveaux montants.');
        $this->assertEquals(60000, $vente->fresh()->reste_a_payer);
        $this->assertSame(StatutPaiement::Partiel, $vente->fresh()->statut_paiement);

        $second = $this->service->enregistrer($vente, 60000, ModePaiement::MobileMoney, 'MVOLA-1');
        $this->assertSame(StatutPaiement::Paye, $vente->fresh()->statut_paiement);
        $this->assertEquals(0, $vente->fresh()->reste_a_payer);

        $this->assertSame(['REC-2026-00001', 'REC-2026-00002'], [$premier->numero, $second->numero]);
        $this->assertTrue($second->payable->is($vente));
        $this->assertSame('vente', DB::table('paiements')->where('id', $second->id)->value('payable_type'));
    }

    public function test_paiement_d_un_achat(): void
    {
        $achat = Achat::factory()->nonSolde(50000)->create();

        $paiement = $this->service->enregistrer($achat, 50000, ModePaiement::Virement, 'VIR-88');

        $this->assertSame('achat', DB::table('paiements')->where('id', $paiement->id)->value('payable_type'));
        $this->assertSame(StatutPaiement::Paye, $achat->fresh()->statut_paiement);
    }

    public function test_montants_refuses(): void
    {
        $vente = Vente::factory()->aCredit(10000)->create();

        foreach ([0, -500, 10001] as $montant) {
            try {
                $this->service->enregistrer($vente, $montant, ModePaiement::Especes);
                $this->fail("Montant {$montant} accepté.");
            } catch (OperationRefuseeException $erreur) {
                $this->assertMatchesRegularExpression('/supérieur à 0|dépasse le reste à payer/', $erreur->getMessage());
            }
        }

        $this->expectException(OperationRefuseeException::class);
        $this->service->enregistrer($vente, 1000, ModePaiement::Credit);
    }

    public function test_document_annule_refuse(): void
    {
        $vente = Vente::factory()->aCredit(10000)->annulee()->create();

        $this->expectException(OperationRefuseeException::class);
        $this->expectExceptionMessage('est annulé');
        $this->service->enregistrer($vente, 1000, ModePaiement::Especes);
    }

    public function test_numero_de_recu_sans_doublon_ni_trou_et_remis_a_zero_chaque_annee(): void
    {
        $vente = Vente::factory()->aCredit(1000000)->create();
        $achat = Achat::factory()->nonSolde(1000000)->create();

        // Ventes et achats partagent la même séquence de reçus
        $numeros = collect(range(1, 6))->map(fn (int $i) => $this->service->enregistrer($i % 2 ? $vente : $achat, 1000, ModePaiement::Especes)->numero);
        $this->assertSame(['REC-2026-00001', 'REC-2026-00002', 'REC-2026-00003', 'REC-2026-00004', 'REC-2026-00005', 'REC-2026-00006'], $numeros->all());

        // Paiement daté de l'année suivante : nouvelle séquence
        Carbon::setTestNow('2027-01-02 08:00:00');
        $this->assertSame('REC-2027-00001', $this->service->enregistrer($vente, 1000, ModePaiement::Especes)->numero);
        $this->assertSame('REC-2027-00002', $this->service->enregistrer($achat, 1000, ModePaiement::Especes)->numero);

        $this->assertSame(Paiement::count(), Paiement::distinct()->count('numero'));
    }

    public function test_un_echec_ne_consomme_aucun_numero(): void
    {
        $vente = Vente::factory()->aCredit(10000)->create();
        $this->service->enregistrer($vente, 1000, ModePaiement::Especes);

        // Transaction parente annulée (ex. vente refusée après le paiement) : le numéro n'est pas consommé
        try {
            DB::transaction(function () use ($vente) {
                $this->service->enregistrer($vente, 1000, ModePaiement::Especes);
                throw new RuntimeException('Vente refusée');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame('REC-2026-00002', $this->service->enregistrer($vente, 1000, ModePaiement::Especes)->numero);
    }

    public function test_paiement_ulterieur_journalise_mais_pas_celui_de_la_caisse(): void
    {
        $vente = Vente::factory()->aCredit(10000)->create();

        $this->service->enregistrer($vente, 1000, ModePaiement::Especes);
        $this->assertDatabaseMissing('journal_activites', ['action' => 'paiement.enregistre']);

        $paiement = $this->service->enregistrerUlterieur($vente, 2000, ModePaiement::Cheque, 'CHQ-7');
        $this->assertDatabaseHas('journal_activites', ['action' => 'paiement.enregistre', 'modele' => 'paiement', 'modele_id' => $paiement->id]);
    }

    public function test_recu_pdf_a5_et_ticket(): void
    {
        $vente = Vente::factory()->create(['total' => 100000, 'montant_paye' => 0, 'reste_a_payer' => 100000]);
        $this->service->enregistrer($vente, 30000, ModePaiement::Especes);
        $paiement = $this->service->enregistrer($vente, 50000, ModePaiement::MobileMoney, 'MVOLA-9');

        // Situation au moment du paiement, même après un règlement suivant
        $this->service->enregistrer($vente, 5000, ModePaiement::Especes);
        $donnees = $this->service->donnees($paiement);
        $this->assertSame(30000.0, $donnees['avant']);
        $this->assertSame(20000.0, $donnees['resteApres']);
        $this->assertTrue($donnees['estVente']);

        $html = view('paiements.recu', $donnees)->render();
        foreach (['REÇU DE PAIEMENT', 'REC-2026-00002', 'MVOLA-9', e($vente->client->nom), 'Reste à payer après ce paiement', "20\u{00A0}000\u{00A0}Ar"] as $attendu) {
            $this->assertStringContainsString($attendu, $html);
        }

        $this->assertStringStartsWith('%PDF', $this->service->pdf($paiement)->output());
        $this->assertStringStartsWith('%PDF', $this->service->pdf($paiement, PaiementService::FORMAT_TICKET)->output());
        $this->assertSame('recu-REC-2026-00002.pdf', $this->service->nomFichier($paiement));
    }

    public function test_recu_d_un_paiement_fournisseur(): void
    {
        $achat = Achat::factory()->nonSolde(80000)->create();
        $paiement = $this->service->enregistrer($achat, 80000, ModePaiement::Virement);

        $html = view('paiements.recu-ticket', $this->service->donnees($paiement))->render();

        $this->assertStringContainsString('PAIEMENT FOURNISSEUR', $html);
        $this->assertStringContainsString(e($achat->fournisseur->nom), $html);
        $this->assertStringContainsString('Soldé', $html);
    }
}
