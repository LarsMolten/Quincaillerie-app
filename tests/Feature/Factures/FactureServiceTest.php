<?php

namespace Tests\Feature\Factures;

use App\Enums\ModePaiement;
use App\Enums\StatutFacture;
use App\Enums\TypeMouvementStock;
use App\Exceptions\StockInsuffisantException;
use App\Models\Client;
use App\Models\Facture;
use App\Models\Parametre;
use App\Models\Produit;
use App\Models\Role;
use App\Models\Utilisateur;
use App\Models\Vente;
use App\Services\FactureService;
use App\Services\MouvementStockService;
use App\Services\VenteService;
use Database\Seeders\ClientComptoirSeeder;
use Database\Seeders\DroitSeeder;
use Database\Seeders\ParametreSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use LogicException;
use Tests\TestCase;

class FactureServiceTest extends TestCase
{
    use RefreshDatabase;

    private Produit $produit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, DroitSeeder::class, ParametreSeeder::class, ClientComptoirSeeder::class]);
        $this->actingAs(Utilisateur::factory()->create(['role_id' => Role::where('nom', RoleSeeder::RESPONSABLE)->firstOrFail()->id]));
        $this->produit = Produit::factory()->create(['nom' => 'Ciment 50 kg', 'stock_actuel' => 0, 'prix_vente' => 38000]);
        app(MouvementStockService::class)->enregistrer($this->produit, TypeMouvementStock::Achat, 100);
    }

    private function vendre(float $quantite = 1): Vente
    {
        return app(VenteService::class)->creer(
            Client::where('nom', Client::COMPTOIR)->firstOrFail(),
            [['produit_id' => $this->produit->id, 'quantite' => $quantite]],
            0,
            ModePaiement::Especes,
            10000000,
        );
    }

    public function test_numerotation_sequentielle_sans_doublon_et_remise_a_zero_annuelle(): void
    {
        Carbon::setTestNow('2026-12-31 18:00:00');
        $numeros = collect(range(1, 5))->map(fn () => $this->vendre()->facture->numero);

        $this->assertSame(['FAC-2026-00001', 'FAC-2026-00002', 'FAC-2026-00003', 'FAC-2026-00004', 'FAC-2026-00005'], $numeros->all());
        $this->assertSame(5, Facture::distinct()->count('numero'), 'Aucun doublon.');

        Carbon::setTestNow('2027-01-01 08:00:00');
        $this->assertSame('FAC-2027-00001', $this->vendre()->facture->numero);

        Carbon::setTestNow();
    }

    public function test_aucun_numero_consomme_si_la_vente_echoue_et_prefixe_parametre(): void
    {
        $this->vendre();

        try {
            $this->vendre(1000); // stock insuffisant : toute la transaction est annulée
            $this->fail('Refus attendu.');
        } catch (StockInsuffisantException) {
        }

        $this->assertSame('FAC-'.now()->year.'-00002', $this->vendre()->facture->numero, 'Pas de trou après un échec.');

        Parametre::where('cle', 'prefixe_facture')->update(['valeur' => 'fq']);
        Parametre::viderCache();
        $this->assertSame('FQ-'.now()->year.'-00001', $this->vendre()->facture->numero);
    }

    public function test_une_facture_emise_n_est_jamais_modifiable(): void
    {
        $facture = $this->vendre()->facture;

        foreach ([['total' => 1], ['numero' => 'FAC-2026-99999'], ['date_emission' => now()->subYear()]] as $modification) {
            try {
                $facture->fresh()->update($modification);
                $this->fail('Modification refusée attendue : '.json_encode(array_keys($modification)));
            } catch (LogicException $e) {
                $this->assertStringContainsString('n\'est pas modifiable', $e->getMessage());
            }
        }

        try {
            $facture->fresh()->delete();
            $this->fail('Suppression refusée attendue.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('ne peut pas être supprimée', $e->getMessage());
        }

        // Seule l'annulation est permise, et elle est définitive
        app(FactureService::class)->annuler($facture);
        $this->assertSame(StatutFacture::Annulee, $facture->fresh()->statut);

        $this->expectException(LogicException::class);
        $facture->fresh()->update(['statut' => StatutFacture::Emise]);
    }

    public function test_tva_comprise_dans_le_total_si_le_taux_est_positif(): void
    {
        $facture = $this->vendre(3)->facture; // 114 000 Ar TTC

        $this->assertNull(app(FactureService::class)->donnees($facture)['tva'], 'Taux 0 : aucune TVA affichée.');

        Parametre::where('cle', 'taux_tva')->update(['valeur' => '20']);
        Parametre::viderCache();
        $tva = app(FactureService::class)->donnees($facture)['tva'];

        $this->assertSame(['taux' => 20.0, 'ht' => 95000.0, 'montant' => 19000.0], $tva);
        $this->assertEquals(114000, $facture->fresh()->total, 'Le total de la facture ne change pas.');
    }

    public function test_pdf_a4_et_ticket_generes(): void
    {
        $facture = $this->vendre()->facture;
        $service = app(FactureService::class);

        $this->assertStringStartsWith('%PDF', $service->pdf($facture)->output());
        $this->assertStringStartsWith('%PDF', $service->pdf($facture, FactureService::FORMAT_TICKET)->output());
        $this->assertSame("facture-{$facture->numero}.pdf", $service->nomFichier($facture));
        $this->assertSame("ticket-{$facture->numero}.pdf", $service->nomFichier($facture, FactureService::FORMAT_TICKET));
    }
}
