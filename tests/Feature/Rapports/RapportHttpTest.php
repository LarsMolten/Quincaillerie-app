<?php

namespace Tests\Feature\Rapports;

use App\Exports\Rapports\RapportExcel;
use App\Models\Droit;
use App\Models\Role;
use App\Models\Utilisateur;
use App\Rapports\Periode;
use App\Rapports\RapportFinances;
use App\Rapports\RapportStock;
use App\Support\Entreprise;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class RapportHttpTest extends TestCase
{
    use JeuConnu, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->withoutVite();
        $this->creerJeuConnu();
        $this->actingAs($this->responsable);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_pages_des_rapports_avec_filtres(): void
    {
        $this->get(route('rapports.ventes'))->assertOk()
            ->assertSee('Rapport des ventes')->assertSee('Période : du 1er au 12 octobre 2026')
            ->assertSee("190\u{00A0}000\u{00A0}Ar", false)->assertSee('Ciment 50 kg')->assertSee('Hery Vendeur')
            ->assertSee('graphiqueRapport', false);
        $this->get(route('rapports.ventes', ['vendeur' => $this->vendeur->id]))->assertSee("40\u{00A0}000\u{00A0}Ar", false)->assertDontSee('Clous 70 mm');
        $this->get(route('rapports.ventes', ['periode' => 'mois_dernier']))->assertSee('du 1er au 30 septembre 2026')->assertSee("80\u{00A0}000\u{00A0}Ar", false);
        $this->get(route('rapports.ventes', ['periode' => 'personnalise', 'du' => '2026-10-05', 'au' => '2026-10-05']))->assertSee('Période : 5 octobre 2026')->assertSee("140\u{00A0}000\u{00A0}Ar", false);

        $this->get(route('rapports.achats'))->assertOk()->assertSee("1\u{00A0}750\u{00A0}000\u{00A0}Ar", false)->assertSee('Holcim Madagascar');
        $this->get(route('rapports.stock'))->assertOk()->assertSee("1\u{00A0}828\u{00A0}000\u{00A0}Ar", false)->assertSee('Tuyau PVC')->assertSee('Jamais vendu');
        $this->get(route('rapports.finances'))->assertOk()->assertSee("-62\u{00A0}000\u{00A0}Ar", false)->assertSee('Compte de résultat');
    }

    public function test_exports_pdf_et_excel(): void
    {
        foreach (['ventes', 'achats', 'stock', 'finances'] as $rapport) {
            $pdf = $this->get(route('rapports.export', [$rapport, 'pdf']))->assertOk()->assertHeader('content-type', 'application/pdf');
            $this->assertStringStartsWith('%PDF', $pdf->getContent(), "PDF du rapport {$rapport}.");
        }

        $json = $this->getJson(route('rapports.export', ['ventes', 'pdf', 'periode' => 'mois']))->assertOk()
            ->assertJsonPath('nom', 'rapport-ventes-2026-10-01_2026-10-12.pdf');
        $this->assertStringStartsWith('%PDF', base64_decode($json->json('pdf')));

        $html = view('rapports.pdf.finances', [
            'titre' => 'Rapport financier', 'periode' => Periode::raccourci('mois'), 'filtres' => [],
            'donnees' => app(RapportFinances::class)->donnees(Periode::raccourci('mois')),
            'entreprise' => Entreprise::donnees(),
        ])->render();
        $this->assertStringContainsString('RAPPORT FINANCIER', $html);
        $this->assertStringNotContainsString("\u{2212}", $html, 'Signe moins ASCII dans les PDF.');

        // Le libellé « Ajustement (−) » du stock (inventaire validé ou retour annulé) passe aussi en trait d'union ASCII
        $mois = Periode::raccourci('mois');
        $stock = view('rapports.pdf.stock', [
            'titre' => 'Rapport de stock', 'periode' => $mois, 'filtres' => [],
            'donnees' => app(RapportStock::class)->donnees($mois), 'entreprise' => Entreprise::donnees(),
        ])->render();
        $this->assertStringContainsString('Retour fournisseur', $stock);
        $this->assertStringNotContainsString("\u{2212}", $stock);

        Excel::fake();
        $this->get(route('rapports.export', ['ventes', 'excel', 'vendeur' => $this->vendeur->id]))->assertOk();
        Excel::assertDownloaded('rapport-ventes-2026-10-01_2026-10-12.xlsx', function (RapportExcel $export) {
            $feuilles = collect($export->sheets());
            $synthese = $feuilles->first()->array();

            return $feuilles->map->title()->all() === ['Synthèse', 'Évolution', 'Par vendeur', 'Par produit']
                && $synthese[0] === ['Période', 'du 1er au 12 octobre 2026']
                && collect($synthese)->contains(['Chiffre d\'affaires net (Ar)', 40000.0])
                && $feuilles[3]->headings()[0] === 'Produit';
        });

        $this->get(route('rapports.export', ['stock', 'excel']))->assertOk();
        Excel::assertDownloaded('rapport-stock-2026-10-01_2026-10-12.xlsx', fn (RapportExcel $export) => count($export->sheets()) === 7
            && count(collect($export->sheets())->last()->array()) === 9);
    }

    public function test_droits_des_rapports(): void
    {
        $this->get('/resultats')->assertNotFound();

        foreach ([RoleSeeder::VENDEUR, RoleSeeder::MAGASINIER] as $role) {
            $this->actingAs(Utilisateur::factory()->create(['role_id' => Role::where('nom', $role)->firstOrFail()->id]));
            foreach (['ventes', 'achats', 'stock', 'finances'] as $rapport) {
                $this->get(route("rapports.{$rapport}"))->assertForbidden();
                $this->get(route('rapports.export', [$rapport, 'pdf']))->assertForbidden();
            }
        }

        // rapports.voir sans finances.voir : rapports oui, finances non
        $analyste = Utilisateur::factory()->create();
        $analyste->role->droits()->attach(Droit::where('code', 'rapports.voir')->value('id'));
        $this->actingAs($analyste);
        $this->get(route('rapports.ventes'))->assertOk();
        $this->get(route('rapports.finances'))->assertForbidden();
        $this->get(route('rapports.export', ['finances', 'excel']))->assertForbidden();

        auth()->logout();
        $this->get(route('rapports.ventes'))->assertRedirect(route('connexion'));
    }
}
