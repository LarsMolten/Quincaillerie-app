<?php

namespace Tests\Feature\Depenses;

use App\Enums\CategorieDepense;
use App\Models\Depense;
use App\Models\Role;
use App\Models\Utilisateur;
use Database\Seeders\DroitSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DepenseTest extends TestCase
{
    use RefreshDatabase;

    private Utilisateur $responsable;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-12 09:00:00');
        $this->withoutVite();
        $this->seed([RoleSeeder::class, DroitSeeder::class]);
        $this->responsable = $this->avecRole(RoleSeeder::RESPONSABLE);
        $this->actingAs($this->responsable);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function avecRole(string $role): Utilisateur
    {
        return Utilisateur::factory()->create(['role_id' => Role::where('nom', $role)->firstOrFail()->id]);
    }

    private function depense(CategorieDepense $categorie, int $montant, string $date, string $libelle = 'Dépense'): Depense
    {
        return Depense::factory()->create(['categorie' => $categorie, 'montant' => $montant, 'date_depense' => $date, 'libelle' => $libelle]);
    }

    public function test_creation_et_modification_en_json(): void
    {
        $donnees = ['categorie' => 'electricite', 'libelle' => 'Facture JIRAMA septembre', 'montant' => '185 000', 'date_depense' => '2026-10-05', 'mode_paiement' => 'mobile_money', 'notes' => 'Réf. 4521'];

        $this->postJson(route('depenses.store'), $donnees)
            ->assertCreated()
            ->assertJsonPath('message', "Dépense « Facture JIRAMA septembre » enregistrée : 185\u{00A0}000\u{00A0}Ar.");

        $depense = Depense::sole();
        $this->assertSame(CategorieDepense::Electricite, $depense->categorie);
        $this->assertEquals(185000, $depense->montant);
        $this->assertTrue($depense->utilisateur->is($this->responsable));

        $this->putJson(route('depenses.update', $depense), [...$donnees, 'montant' => '190000', 'categorie' => 'eau'])
            ->assertOk()->assertJsonPath('message', 'Dépense « Facture JIRAMA septembre » modifiée.');
        $this->assertEquals(190000, $depense->fresh()->montant);
        $this->assertSame(CategorieDepense::Eau, $depense->fresh()->categorie);
    }

    public function test_validation_en_francais(): void
    {
        $donnees = ['categorie' => 'loyer', 'libelle' => 'Loyer octobre', 'montant' => '600000', 'date_depense' => '2026-10-01', 'mode_paiement' => 'especes'];

        $this->postJson(route('depenses.store'), [...$donnees, 'montant' => '0'])->assertStatus(422)->assertJsonValidationErrors(['montant' => 'Le montant doit être supérieur à 0.']);
        $this->postJson(route('depenses.store'), [...$donnees, 'date_depense' => '2026-10-13'])->assertStatus(422)->assertJsonValidationErrors(['date_depense' => 'La date ne peut pas être dans le futur.']);
        $this->postJson(route('depenses.store'), [...$donnees, 'categorie' => 'vacances'])->assertStatus(422)->assertJsonValidationErrors(['categorie' => 'Choisissez une catégorie dans la liste.']);
        $this->postJson(route('depenses.store'), [...$donnees, 'libelle' => '  '])->assertStatus(422)->assertJsonValidationErrors(['libelle' => 'Indiquez le libellé de la dépense.']);
        $this->postJson(route('depenses.store'), [...$donnees, 'mode_paiement' => 'credit'])->assertStatus(422)->assertJsonValidationErrors('mode_paiement');
        $this->assertSame(0, Depense::count());
    }

    public function test_liste_filtres_total_et_repartition(): void
    {
        $this->depense(CategorieDepense::Loyer, 600000, '2026-10-01', 'Loyer octobre');
        $this->depense(CategorieDepense::Electricite, 150000, '2026-10-05', 'JIRAMA électricité');
        $this->depense(CategorieDepense::Electricite, 50000, '2026-10-10', 'JIRAMA complément');
        $this->depense(CategorieDepense::Salaires, 900000, '2026-09-30', 'Salaires septembre');

        // Ce mois (défaut) : 800 000 ; répartition loyer 75 %, électricité 25 %
        $this->get(route('depenses.index'))->assertOk()
            ->assertSee("800\u{00A0}000\u{00A0}Ar", false)
            ->assertSee('Loyer octobre')->assertDontSee('Salaires septembre')
            ->assertSeeInOrder(['Loyer', "600\u{00A0}000\u{00A0}Ar", '75,0 %', 'Électricité', "200\u{00A0}000\u{00A0}Ar", '25,0 %'], false)
            ->assertSee('graphiqueAnneau', false);

        $this->get(route('depenses.index', ['categorie' => 'electricite']))
            ->assertSee('Total Électricité')->assertSee("200\u{00A0}000\u{00A0}Ar", false)->assertDontSee('Loyer octobre');
        $this->get(route('depenses.index', ['periode' => 'mois_dernier']))->assertSee('Salaires septembre')->assertDontSee('Loyer octobre');
        $this->get(route('depenses.index', ['periode' => 'tout']))->assertSee("1\u{00A0}700\u{00A0}000\u{00A0}Ar", false);
        $this->get(route('depenses.index', ['du' => '2026-10-04', 'au' => '2026-10-06']))->assertSee('JIRAMA électricité')->assertDontSee('JIRAMA complément');
        $this->get(route('depenses.index', ['recherche' => 'complément']))->assertSee('JIRAMA complément')->assertDontSee('Loyer octobre');

        $fragment = $this->get(route('depenses.index'), ['X-Fragment' => 'liste'])->getContent();
        $this->assertStringNotContainsString('<!DOCTYPE', $fragment);
        $this->assertStringContainsString('Répartition par catégorie', $fragment, 'Le total et l\'anneau sont rechargés avec la liste.');
    }

    public function test_suppression_reservee_a_l_administrateur_douce_et_journalisee(): void
    {
        $depense = $this->depense(CategorieDepense::Transport, 40000, '2026-10-08', 'Taxi-brousse livraison');

        // Responsable : pas de bouton, refus
        $this->get(route('depenses.index'))->assertDontSee('modale-suppression-depense');
        $this->deleteJson(route('depenses.destroy', $depense))->assertForbidden()->assertJsonPath('message', 'Seul l\'Administrateur peut supprimer une dépense.');
        $this->assertNotSoftDeleted($depense);

        // Administrateur
        $this->actingAs($this->avecRole(Role::ADMINISTRATEUR));
        $this->get(route('depenses.index'))->assertSee('modale-suppression-depense');
        $this->deleteJson(route('depenses.destroy', $depense))->assertOk()->assertJsonPath('message', 'Dépense « Taxi-brousse livraison » supprimée.');

        $this->assertSoftDeleted($depense);
        $this->assertDatabaseHas('journal_activites', ['action' => 'depense.supprimee', 'modele' => 'depense', 'modele_id' => $depense->id]);
        $this->get(route('depenses.index'))->assertDontSee('Taxi-brousse livraison')->assertSee('Aucune dépense ce mois-ci');
        $this->deleteJson(route('depenses.destroy', $depense))->assertNotFound();
    }

    public function test_droits(): void
    {
        $depense = $this->depense(CategorieDepense::Eau, 30000, '2026-10-02');

        foreach ([RoleSeeder::VENDEUR, RoleSeeder::MAGASINIER] as $role) {
            $this->actingAs($this->avecRole($role));
            $this->get(route('depenses.index'))->assertForbidden();
            $this->postJson(route('depenses.store'), [])->assertForbidden();
            $this->putJson(route('depenses.update', $depense), [])->assertForbidden();
            $this->deleteJson(route('depenses.destroy', $depense))->assertForbidden();
        }

        auth()->logout();
        $this->get(route('depenses.index'))->assertRedirect(route('connexion'));
    }
}
