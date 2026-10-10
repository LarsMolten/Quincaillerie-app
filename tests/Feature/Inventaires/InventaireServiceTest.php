<?php

namespace Tests\Feature\Inventaires;

use App\Enums\ModePaiement;
use App\Enums\StatutInventaire;
use App\Enums\TypeMouvementStock;
use App\Exceptions\OperationRefuseeException;
use App\Models\Categorie;
use App\Models\Client;
use App\Models\Inventaire;
use App\Models\MouvementStock;
use App\Models\Produit;
use App\Models\Role;
use App\Models\Utilisateur;
use App\Services\InventaireService;
use App\Services\MouvementStockService;
use App\Services\VenteService;
use Database\Seeders\ClientComptoirSeeder;
use Database\Seeders\DroitSeeder;
use Database\Seeders\ParametreSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use LogicException;
use Tests\TestCase;

class InventaireServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventaireService $service;

    private Categorie $ciments;

    private Categorie $quincaillerie;

    private Produit $ciment;

    private Produit $clous;

    private Produit $vis;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-11 08:00:00');
        $this->seed([RoleSeeder::class, DroitSeeder::class, ParametreSeeder::class, ClientComptoirSeeder::class]);
        $this->actingAs(Utilisateur::factory()->create(['role_id' => Role::where('nom', RoleSeeder::MAGASINIER)->firstOrFail()->id]));
        $this->service = app(InventaireService::class);

        $this->ciments = Categorie::factory()->create(['nom' => 'Ciments']);
        $this->quincaillerie = Categorie::factory()->create(['nom' => 'Quincaillerie']);
        $this->ciment = $this->produit('Ciment 50 kg', $this->ciments, 40, 33000);
        $this->clous = $this->produit('Clous 70 mm', $this->quincaillerie, 20, 6000);
        $this->vis = $this->produit('Vis 4 × 40', $this->quincaillerie, 100, 50);
        Produit::factory()->create(['nom' => 'Ancien produit', 'actif' => false, 'categorie_id' => $this->ciments->id, 'stock_actuel' => 0]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function produit(string $nom, Categorie $categorie, float $stock, float $prixAchat): Produit
    {
        $produit = Produit::factory()->create(['nom' => $nom, 'categorie_id' => $categorie->id, 'stock_actuel' => 0, 'prix_achat' => $prixAchat, 'prix_vente' => $prixAchat * 1.2, 'actif' => true]);
        app(MouvementStockService::class)->enregistrer($produit, TypeMouvementStock::Achat, $stock);

        return $produit->refresh();
    }

    private function ligne(Inventaire $inventaire, Produit $produit)
    {
        return $inventaire->lignes()->where('produit_id', $produit->id)->sole();
    }

    private function refus(callable $action, string $message): void
    {
        try {
            $action();
            $this->fail("Refus attendu : {$message}");
        } catch (OperationRefuseeException|LogicException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }

    public function test_un_inventaire_valide_corrige_bien_le_stock(): void
    {
        $inventaire = $this->service->ouvrir();

        $this->assertSame('INV-2026-00001', $inventaire->numero);
        $this->assertSame(3, $inventaire->lignes()->count(), 'Produits actifs seulement.');
        $this->assertEquals(40, $this->ligne($inventaire, $this->ciment)->stock_theorique);

        $this->service->compter($inventaire, $this->ligne($inventaire, $this->ciment), 37);   // 3 sacs manquants
        $this->service->compter($inventaire, $this->ligne($inventaire, $this->clous), 22.5);  // 2,5 kg en trop
        $this->service->compter($inventaire, $this->ligne($inventaire, $this->vis), 100);     // juste
        $this->assertEquals(-3, $this->ligne($inventaire, $this->ciment)->ecart, 'Écart provisoire affiché pendant le comptage.');

        $this->service->valider($inventaire);

        $this->assertSame(StatutInventaire::Valide, $inventaire->fresh()->statut);
        $this->assertEquals(37, $this->ciment->fresh()->stock_actuel);
        $this->assertEquals(22.5, $this->clous->fresh()->stock_actuel);
        $this->assertEquals(100, $this->vis->fresh()->stock_actuel);

        $mouvements = MouvementStock::where('reference_type', 'inventaire')->where('reference_id', $inventaire->id)->get()->keyBy('produit_id');
        $this->assertCount(2, $mouvements, 'Aucun mouvement pour un écart nul.');
        $this->assertSame(TypeMouvementStock::AjustementNegatif, $mouvements[$this->ciment->id]->type);
        $this->assertEquals(3, $mouvements[$this->ciment->id]->quantite);
        $this->assertSame(TypeMouvementStock::AjustementPositif, $mouvements[$this->clous->id]->type);
        $this->assertEquals(2.5, $mouvements[$this->clous->id]->quantite);
        $this->assertSame('Inventaire INV-2026-00001', $mouvements[$this->ciment->id]->motif);

        $this->assertSame([], app(MouvementStockService::class)->verifierCoherence()->all(), 'Stock cohérent avec les mouvements.');
        $this->assertDatabaseHas('journal_activites', ['action' => 'inventaire.valide', 'modele' => 'inventaire', 'modele_id' => $inventaire->id]);
    }

    public function test_l_ecart_porte_sur_le_stock_au_moment_de_la_validation(): void
    {
        $inventaire = $this->service->ouvrir();
        $this->service->compter($inventaire, $this->ligne($inventaire, $this->ciment), 35);

        // Vente de 5 sacs pendant le comptage : le stock réel passe de 40 à 35
        app(VenteService::class)->creer(Client::where('nom', Client::COMPTOIR)->firstOrFail(), [['produit_id' => $this->ciment->id, 'quantite' => 5]], 0, ModePaiement::Especes, 1000000);

        $this->service->valider($inventaire, true);

        $this->assertEquals(35, $this->ciment->fresh()->stock_actuel);
        $this->assertEquals(0, $this->ligne($inventaire, $this->ciment)->ecart);
        $this->assertSame(0, MouvementStock::where('reference_type', 'inventaire')->count(), 'Le comptage correspond au stock réel : rien à corriger.');
        $this->assertEquals(40, $this->ligne($inventaire, $this->ciment)->stock_theorique, 'Le stock figé à l\'ouverture reste pour information.');
    }

    public function test_les_non_comptes_sont_ignores_seulement_avec_confirmation(): void
    {
        $inventaire = $this->service->ouvrir();
        $this->refus(fn () => $this->service->valider($inventaire), 'Aucun produit n\'a été compté');

        $this->service->compter($inventaire, $this->ligne($inventaire, $this->ciment), 30);
        $this->refus(fn () => $this->service->valider($inventaire), '2 produit(s) ne sont pas comptés');
        $this->assertSame(StatutInventaire::EnCours, $inventaire->fresh()->statut);

        $this->service->valider($inventaire, true);
        $this->assertEquals(30, $this->ciment->fresh()->stock_actuel);
        $this->assertEquals(20, $this->clous->fresh()->stock_actuel, 'Non compté : stock inchangé.');
        $this->assertNull($this->ligne($inventaire, $this->clous)->ecart);
    }

    public function test_perimetre_par_categorie_et_un_seul_inventaire_en_cours_par_produit(): void
    {
        $quincaillerie = $this->service->ouvrir([$this->quincaillerie->id], 'Rayon visserie');
        $this->assertEqualsCanonicalizing([$this->clous->id, $this->vis->id], $quincaillerie->lignes()->pluck('produit_id')->all());
        $this->assertSame('Rayon visserie', $quincaillerie->notes);

        $this->refus(fn () => $this->service->ouvrir(), '« Clous 70 mm » est déjà dans l\'inventaire en cours INV-2026-00001');
        $ciments = $this->service->ouvrir([$this->ciments->id]);
        $this->assertSame('INV-2026-00002', $ciments->numero, 'Numéro sans trou malgré le refus.');

        $this->refus(fn () => $this->service->ouvrir([Categorie::factory()->create()->id]), 'Aucun produit actif');
    }

    public function test_un_inventaire_valide_n_est_plus_modifiable(): void
    {
        $inventaire = $this->service->ouvrir();
        $ligne = $this->ligne($inventaire, $this->ciment);
        $this->service->compter($inventaire, $ligne, 39);
        $this->service->valider($inventaire, true);

        $this->refus(fn () => $this->service->compter($inventaire->fresh(), $ligne->fresh(), 10), 'n\'est plus modifiable');
        $this->refus(fn () => $this->service->valider($inventaire->fresh(), true), 'n\'est plus modifiable');
        $this->refus(fn () => $inventaire->fresh()->update(['notes' => 'Retouche']), 'n\'est plus modifiable');
        $this->refus(fn () => $ligne->fresh()->update(['stock_compte' => 1]), 'ne sont plus modifiables');
        $this->refus(fn () => $inventaire->fresh()->delete(), 'ne peut pas être supprimé');
        $this->assertEquals(39, $this->ciment->fresh()->stock_actuel);

        // Le produit peut entrer dans un nouvel inventaire
        $this->assertSame('INV-2026-00002', $this->service->ouvrir()->numero);
    }

    public function test_quantite_negative_refusee_et_remise_a_non_compte(): void
    {
        $inventaire = $this->service->ouvrir();
        $ligne = $this->ligne($inventaire, $this->clous);

        $this->refus(fn () => $this->service->compter($inventaire, $ligne, -1), 'négative');
        $this->service->compter($inventaire, $ligne, 18);
        $this->service->compter($inventaire, $ligne, null);
        $this->assertNull($ligne->fresh()->stock_compte);
        $this->assertNull($ligne->fresh()->ecart);
    }

    public function test_import_csv(): void
    {
        $inventaire = $this->service->ouvrir();
        $csv = "\u{FEFF}reference;quantite\n{$this->ciment->reference};38\n{$this->clous->reference};19,5\nINCONNU-1;4\n{$this->vis->reference};abc\n\n";

        $bilan = $this->service->importerCsv($inventaire, UploadedFile::fake()->createWithContent('comptage.csv', $csv));

        $this->assertSame(2, $bilan['importees']);
        $this->assertSame(['INCONNU-1'], $bilan['inconnues']);
        $this->assertSame(["{$this->vis->reference} (abc)"], $bilan['invalides']);
        $this->assertEquals(38, $this->ligne($inventaire, $this->ciment)->stock_compte);
        $this->assertEquals(19.5, $this->ligne($inventaire, $this->clous)->stock_compte);

        // Séparateur virgule, sans en-tête, par code-barres
        $this->vis->forceFill(['code_barres' => '3760001234567'])->saveQuietly();
        $bilan = $this->service->importerCsv($inventaire, UploadedFile::fake()->createWithContent('scan.csv', "3760001234567,96\n"));
        $this->assertSame(1, $bilan['importees']);
        $this->assertEquals(96, $this->ligne($inventaire, $this->vis)->stock_compte);
    }

    public function test_pdf_feuille_et_rapport(): void
    {
        $inventaire = $this->service->ouvrir();
        $this->service->compter($inventaire, $this->ligne($inventaire, $this->ciment), 37);

        $this->assertStringStartsWith('%PDF', $this->service->pdf($inventaire, 'feuille')->output());
        $this->assertStringStartsWith('%PDF', $this->service->pdf($inventaire, 'rapport')->output());
    }
}
