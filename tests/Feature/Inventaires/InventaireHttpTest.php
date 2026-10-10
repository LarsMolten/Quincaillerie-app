<?php

namespace Tests\Feature\Inventaires;

use App\Enums\StatutInventaire;
use App\Enums\TypeMouvementStock;
use App\Models\Categorie;
use App\Models\Inventaire;
use App\Models\Produit;
use App\Models\Role;
use App\Models\Utilisateur;
use App\Services\MouvementStockService;
use Database\Seeders\DroitSeeder;
use Database\Seeders\ParametreSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InventaireHttpTest extends TestCase
{
    use RefreshDatabase;

    private Produit $ciment;

    private Produit $clous;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-11 08:00:00');
        $this->withoutVite();
        $this->seed([RoleSeeder::class, DroitSeeder::class, ParametreSeeder::class]);
        $this->actingAs(Utilisateur::factory()->create(['role_id' => Role::where('nom', RoleSeeder::MAGASINIER)->firstOrFail()->id]));
        $categorie = Categorie::factory()->create(['nom' => 'Ciments']);
        $this->ciment = Produit::factory()->create(['nom' => 'Ciment 50 kg', 'reference' => 'CIM-050', 'categorie_id' => $categorie->id, 'stock_actuel' => 0, 'actif' => true]);
        $this->clous = Produit::factory()->create(['nom' => 'Clous 70 mm', 'reference' => 'CLO-070', 'stock_actuel' => 0, 'actif' => true]);
        app(MouvementStockService::class)->enregistrer($this->ciment, TypeMouvementStock::Achat, 40);
        app(MouvementStockService::class)->enregistrer($this->clous, TypeMouvementStock::Achat, 20);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_parcours_complet_par_les_routes(): void
    {
        $this->get(route('inventaires.index'))->assertOk()->assertSee('Aucun inventaire')->assertSee('Ciments');

        $this->post(route('inventaires.store'), ['perimetre' => 'categories', 'categories' => []])->assertSessionHasErrors(['categories' => 'Cochez au moins une catégorie.']);
        $this->post(route('inventaires.store'), ['perimetre' => 'tous', 'notes' => 'Fin de mois'])
            ->assertRedirect(route('inventaires.show', 1))
            ->assertSessionHas('succes', 'Inventaire INV-2026-00001 ouvert : 2 produit(s) à compter.');

        $inventaire = Inventaire::sole();
        $ligneCiment = $inventaire->lignes()->where('produit_id', $this->ciment->id)->sole();

        $this->get(route('inventaires.show', $inventaire))->assertOk()
            ->assertSee('Inventaire INV-2026-00001')->assertSee('Ciment 50 kg')->assertSee('Valider l\'inventaire', false);
        $this->get(route('inventaires.index'))->assertSee('INV-2026-00001')->assertSee('0/2');

        $this->patchJson(route('inventaires.lignes.update', [$inventaire, $ligneCiment]), ['quantite' => '37,5'])
            ->assertOk()->assertJsonPath('compte', 37.5)->assertJsonPath('ecart', -2.5);
        $this->patchJson(route('inventaires.lignes.update', [$inventaire, $ligneCiment]), ['quantite' => '-1'])
            ->assertStatus(422)->assertJsonValidationErrors(['quantite' => 'La quantité comptée ne peut pas être négative.']);

        // Import CSV pour les clous
        $this->post(route('inventaires.import', $inventaire), ['fichier' => UploadedFile::fake()->createWithContent('comptage.csv', "reference;quantite\nCLO-070;21\nXXX;3\n")])
            ->assertSessionHas('alerte', '1 comptage(s) importé(s). 1 référence(s) hors inventaire : XXX');

        // Rapport d'écarts et feuille de comptage
        $this->assertStringStartsWith('%PDF', $this->get(route('inventaires.pdf', [$inventaire, 'feuille']))->assertOk()->getContent());
        $this->assertStringStartsWith('%PDF', base64_decode($this->getJson(route('inventaires.pdf', [$inventaire, 'rapport']))->assertOk()->json('pdf')));

        $this->post(route('inventaires.valider', $inventaire))->assertRedirect(route('inventaires.show', $inventaire))
            ->assertSessionHas('succes', 'Inventaire INV-2026-00001 validé : stock corrigé.');

        $this->assertSame(StatutInventaire::Valide, $inventaire->fresh()->statut);
        $this->assertEquals(37.5, $this->ciment->fresh()->stock_actuel);
        $this->assertEquals(21, $this->clous->fresh()->stock_actuel);

        // Validé : lecture seule
        $this->get(route('inventaires.show', $inventaire))->assertSee('Inventaire validé')->assertDontSee('Importer CSV');
        $this->patchJson(route('inventaires.lignes.update', [$inventaire, $ligneCiment]), ['quantite' => '1'])
            ->assertStatus(422)->assertJsonPath('errors.quantite.0', 'L\'inventaire INV-2026-00001 est validé : il n\'est plus modifiable.');
    }

    public function test_validation_avec_non_comptes_demande_confirmation(): void
    {
        $this->post(route('inventaires.store'), ['perimetre' => 'tous']);
        $inventaire = Inventaire::sole();
        $this->patchJson(route('inventaires.lignes.update', [$inventaire, $inventaire->lignes()->where('produit_id', $this->ciment->id)->sole()]), ['quantite' => '39']);

        $this->post(route('inventaires.valider', $inventaire))->assertSessionHas('erreur');
        $this->assertSame(StatutInventaire::EnCours, $inventaire->fresh()->statut);

        $this->post(route('inventaires.valider', $inventaire), ['confirmer_non_comptes' => '1'])->assertSessionHas('succes');
        $this->assertEquals(39, $this->ciment->fresh()->stock_actuel);
        $this->assertEquals(20, $this->clous->fresh()->stock_actuel);
    }

    public function test_une_ligne_d_un_autre_inventaire_est_introuvable(): void
    {
        $this->post(route('inventaires.store'), ['perimetre' => 'categories', 'categories' => [$this->ciment->categorie_id]]);
        $this->post(route('inventaires.store'), ['perimetre' => 'tous'])->assertSessionHas('erreur');
        [$premier] = Inventaire::all();
        $autre = Inventaire::factory()->create();
        $ligneAutre = $autre->lignes()->create(['produit_id' => $this->clous->id, 'stock_theorique' => 20]);

        $this->patchJson(route('inventaires.lignes.update', [$premier, $ligneAutre]), ['quantite' => '1'])->assertNotFound();
    }

    public function test_droits(): void
    {
        $this->actingAs(Utilisateur::factory()->create(['role_id' => Role::where('nom', RoleSeeder::VENDEUR)->firstOrFail()->id]));
        $this->get(route('inventaires.index'))->assertForbidden();
        $this->post(route('inventaires.store'), ['perimetre' => 'tous'])->assertForbidden();

        auth()->logout();
        $this->get(route('inventaires.index'))->assertRedirect(route('connexion'));
    }
}
