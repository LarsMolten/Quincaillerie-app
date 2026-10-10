<?php

namespace Tests\Feature\Stock;

use App\Enums\ModePaiement;
use App\Enums\TypeMouvementStock;
use App\Exports\MouvementsExport;
use App\Models\Client;
use App\Models\Fournisseur;
use App\Models\Produit;
use App\Models\Role;
use App\Models\Utilisateur;
use App\Services\AchatService;
use App\Services\InventaireService;
use App\Services\MouvementStockService;
use App\Services\RetourService;
use App\Services\VenteService;
use Database\Seeders\ClientComptoirSeeder;
use Database\Seeders\DroitSeeder;
use Database\Seeders\ParametreSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class MouvementsTest extends TestCase
{
    use RefreshDatabase;

    private Produit $ciment;

    private Produit $clous;

    private Utilisateur $responsable;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-11 10:00:00');
        $this->withoutVite();
        $this->seed([RoleSeeder::class, DroitSeeder::class, ParametreSeeder::class, ClientComptoirSeeder::class]);
        $this->responsable = Utilisateur::factory()->create(['nom' => 'Rasoa Responsable', 'role_id' => Role::where('nom', RoleSeeder::RESPONSABLE)->firstOrFail()->id]);
        $this->actingAs($this->responsable);
        $this->ciment = Produit::factory()->create(['nom' => 'Ciment 50 kg', 'reference' => 'CIM-050', 'stock_actuel' => 0, 'prix_vente' => 40000]);
        $this->clous = Produit::factory()->create(['nom' => 'Clous 70 mm', 'reference' => 'CLO-070', 'stock_actuel' => 0]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_liste_filtres_et_references_cliquables(): void
    {
        $achat = app(AchatService::class)->creer(Fournisseur::factory()->create(['actif' => true]), [['produit_id' => $this->ciment->id, 'quantite' => 50, 'prix_achat' => 33000]], 0, null);
        Carbon::setTestNow('2026-10-11 11:00:00');
        $vente = app(VenteService::class)->creer(Client::where('nom', Client::COMPTOIR)->firstOrFail(), [['produit_id' => $this->ciment->id, 'quantite' => 3]], 0, ModePaiement::Especes, 200000);
        $retour = app(RetourService::class)->creerClient($vente, [$this->ciment->id => 1], 'Produit défectueux', ModePaiement::Especes);
        Carbon::setTestNow('2026-09-01 09:00:00');
        app(MouvementStockService::class)->enregistrer($this->clous, TypeMouvementStock::AjustementPositif, 10, null, 'Stock retrouvé en réserve');
        Carbon::setTestNow('2026-10-11 12:00:00');

        $page = $this->get(route('stock.mouvements'))->assertOk()
            ->assertSee('Ciment 50 kg')->assertSee('Clous 70 mm')
            ->assertSee(route('achats.show', $achat), false)
            ->assertSee(route('ventes.show', $vente), false)
            ->assertSee(route('retours.show', $retour), false)
            ->assertSee('Saisie manuelle')
            ->assertSee('Stock retrouvé en réserve');
        $page->assertSeeInOrder(['50', '→', '47'], false);

        $this->get(route('stock.mouvements', ['type' => 'vente']))->assertSee($vente->numero)->assertDontSee($achat->numero);
        $this->get(route('stock.mouvements', ['sens' => 'sortie']))->assertSee($vente->numero)->assertDontSee('Stock retrouvé');
        $this->get(route('stock.mouvements', ['periode' => 'mois']))->assertSee('Ciment 50 kg')->assertDontSee('Stock retrouvé');
        $this->get(route('stock.mouvements', ['du' => '2026-09-01', 'au' => '2026-09-30']))->assertSee('Stock retrouvé')->assertDontSee($vente->numero);
        $this->get(route('stock.mouvements', ['recherche' => 'CLO-070']))->assertSee('Clous 70 mm')->assertDontSee('Ciment 50 kg');
        $this->get(route('stock.mouvements', ['produit' => $this->ciment->id]))->assertSee('Produit : Ciment 50 kg')->assertDontSee('Clous 70 mm');
        $this->get(route('stock.mouvements', ['utilisateur' => $this->responsable->id]))->assertSee('Ciment 50 kg');
        $this->get(route('stock.mouvements', ['utilisateur' => 999]))->assertSee('Aucun mouvement trouvé');

        $fragment = $this->get(route('stock.mouvements', ['sens' => 'entree']), ['X-Fragment' => 'liste'])->getContent();
        $this->assertStringNotContainsString('<!DOCTYPE', $fragment);
        $this->assertStringContainsString($achat->numero, $fragment);
    }

    public function test_entrees_et_sorties_imposent_le_sens(): void
    {
        app(MouvementStockService::class)->enregistrer($this->ciment, TypeMouvementStock::Achat, 20, null, 'Réception');
        app(MouvementStockService::class)->enregistrer($this->ciment, TypeMouvementStock::Perte, 2, null, 'Sacs percés');

        $this->get(route('stock.entrees'))->assertOk()->assertSee('Entrées de stock')->assertSee('Réception')->assertDontSee('Sacs percés')
            ->assertSee('Ajustement d’entrée');
        $this->get(route('stock.sorties', ['sens' => 'entree']))->assertOk()->assertSee('Sacs percés')->assertDontSee('Réception')
            ->assertSee('Perte ou ajustement');
    }

    public function test_reference_inventaire_cliquable(): void
    {
        app(MouvementStockService::class)->enregistrer($this->ciment, TypeMouvementStock::Achat, 20);
        $inventaire = app(InventaireService::class)->ouvrir();
        app(InventaireService::class)->compter($inventaire, $inventaire->lignes()->where('produit_id', $this->ciment->id)->sole(), 18);
        app(InventaireService::class)->valider($inventaire, true);

        $this->get(route('stock.mouvements', ['type' => 'ajustement_negatif']))->assertSee(route('inventaires.show', $inventaire), false)->assertSee($inventaire->numero);
    }

    public function test_export_excel_avec_les_filtres(): void
    {
        Excel::fake();
        app(MouvementStockService::class)->enregistrer($this->ciment, TypeMouvementStock::Achat, 20, null, 'Réception');
        app(MouvementStockService::class)->enregistrer($this->clous, TypeMouvementStock::Achat, 5, null, 'Réception');
        app(MouvementStockService::class)->enregistrer($this->clous, TypeMouvementStock::Perte, 0.5, null, 'Rouille');

        $this->get(route('stock.mouvements.export', ['sens' => 'sortie']))->assertOk();

        Excel::assertDownloaded('mouvements-2026-10-11.xlsx', function (MouvementsExport $export) {
            $lignes = $export->query()->get();
            $premiere = $export->map($lignes->first());

            return $lignes->count() === 1
                && $export->headings()[0] === 'Date' && in_array('Stock après', $export->headings(), true)
                && $premiere[1] === 'Clous 70 mm' && $premiere[3] === 'Perte' && $premiere[4] === 'Sortie' && $premiere[5] === -0.5
                && $premiere[11] === 'Rouille';
        });
    }

    public function test_droits(): void
    {
        $this->actingAs(Utilisateur::factory()->create(['role_id' => Role::where('nom', RoleSeeder::VENDEUR)->firstOrFail()->id]));
        $this->get(route('stock.mouvements'))->assertForbidden();
        $this->get(route('stock.entrees'))->assertForbidden();
        $this->get(route('stock.mouvements.export'))->assertForbidden();

        $this->actingAs(Utilisateur::factory()->create(['role_id' => Role::where('nom', RoleSeeder::MAGASINIER)->firstOrFail()->id]));
        $this->get(route('stock.mouvements'))->assertOk();
    }
}
