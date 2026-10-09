<?php

namespace Tests\Feature\Seeders;

use App\Enums\ModePaiement;
use App\Models\Achat;
use App\Models\Client;
use App\Models\Facture;
use App\Models\Fournisseur;
use App\Models\MouvementStock;
use App\Models\Produit;
use App\Models\Vente;
use App\Services\MouvementStockService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'quincaillerie.admin.mot_de_passe' => 'MotDePasseDeTest!',
            'quincaillerie.demo' => true,
        ]);

        $this->seed(DatabaseSeeder::class);
    }

    public function test_volumes_de_demonstration(): void
    {
        $this->assertGreaterThanOrEqual(60, Produit::count());
        $this->assertSame(10, Fournisseur::count());
        $this->assertSame(31, Client::count()); // 30 clients + Client comptoir
        $this->assertGreaterThan(50, Vente::count());
        $this->assertGreaterThan(5, Achat::count());
        $this->assertSame(Vente::count(), Facture::count());
    }

    public function test_les_documents_couvrent_les_30_derniers_jours(): void
    {
        $this->assertTrue(Vente::min('date_vente') >= today()->subDays(30)->toDateTimeString());
        $this->assertTrue(Vente::max('date_vente') <= now()->toDateTimeString());
        $this->assertTrue(Vente::whereDate('date_vente', '>=', today()->subDays(7))->exists());
    }

    public function test_le_stock_correspond_exactement_aux_mouvements(): void
    {
        // Contrôle officiel : somme des mouvements et chaîne stock_avant / stock_apres
        $this->assertTrue(app(MouvementStockService::class)->verifierCoherence()->isEmpty());
        $this->assertSame(0, Produit::where('stock_actuel', '<', 0)->count());

        // Chaque ligne de vente et d'achat a son mouvement de stock
        $this->assertSame(DB::table('lignes_vente')->count(), MouvementStock::where('type', 'vente')->count());
        $this->assertSame(DB::table('lignes_achat')->count(), MouvementStock::where('type', 'achat')->count());
    }

    public function test_les_numeros_sont_sequentiels_sans_trou(): void
    {
        $numeros = Vente::orderBy('id')->pluck('numero');
        $annee = now()->year;

        foreach ($numeros as $index => $numero) {
            $this->assertSame(sprintf('VTE-%d-%05d', $annee, $index + 1), $numero);
        }
    }

    public function test_coherence_des_montants_et_regles_de_credit(): void
    {
        foreach (Vente::with('lignes', 'client')->get() as $vente) {
            $this->assertEqualsWithDelta((float) $vente->lignes->sum('total'), (float) $vente->total, 0.01);
            $this->assertEqualsWithDelta((float) $vente->total, $vente->montant_paye + $vente->reste_a_payer, 0.01);

            if ($vente->mode_paiement === ModePaiement::Credit) {
                $this->assertFalse($vente->client->estComptoir(), 'Le Client comptoir ne peut pas acheter à crédit.');
            }
        }

        foreach (Client::whereNotNull('plafond_credit')->get() as $client) {
            $this->assertLessThanOrEqual((float) $client->plafond_credit, $client->creance_totale);
        }
    }

    public function test_les_marges_sont_positives(): void
    {
        $this->assertSame(0, Produit::whereColumn('prix_vente', '<=', 'prix_achat')->count());
    }

    public function test_relancer_la_demo_ne_cree_pas_de_doublons(): void
    {
        $ventes = Vente::count();

        $this->seed(DemoSeeder::class);

        $this->assertSame($ventes, Vente::count());
    }
}
