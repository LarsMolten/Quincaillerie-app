<?php

namespace Tests\Feature\Categories;

use App\Models\Categorie;
use App\Models\Produit;
use App\Models\Role;
use App\Models\Utilisateur;
use Database\Seeders\DroitSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class CategorieTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed([RoleSeeder::class, DroitSeeder::class]);
        $this->actingAs($this->avecRole(RoleSeeder::RESPONSABLE));
    }

    private function avecRole(string $role): Utilisateur
    {
        return Utilisateur::factory()->create(['role_id' => Role::where('nom', $role)->firstOrFail()->id]);
    }

    public function test_la_liste_affiche_les_categories_et_leur_nombre_de_produits(): void
    {
        $plomberie = Categorie::factory()->create(['nom' => 'Plomberie']);
        Produit::factory()->count(3)->for($plomberie)->create();
        Categorie::factory()->create(['nom' => 'Serrurerie']);

        $this->get(route('categories.index'))
            ->assertOk()
            ->assertSee('Plomberie')
            ->assertSee('Serrurerie')
            ->assertSeeInOrder(['Plomberie', '3', 'produits'])
            ->assertSee('Produits classés')
            ->assertSee('Catégories sans produit')
            ->assertSee('aria-current="page"', false);
    }

    public function test_recherche_et_fragment_sans_layout(): void
    {
        Categorie::factory()->create(['nom' => 'Plomberie', 'description' => 'Tuyaux et raccords']);
        Categorie::factory()->create(['nom' => 'Électricité']);

        $this->get(route('categories.index', ['recherche' => 'tuyau']))
            ->assertSee('Plomberie')
            ->assertDontSee('Électricité');

        $fragment = $this->get(route('categories.index', ['recherche' => 'plomb']), ['X-Fragment' => 'liste'])
            ->assertOk()
            ->assertSee('Plomberie');

        $this->assertStringNotContainsString('<!DOCTYPE', $fragment->getContent());
        $this->assertStringNotContainsString('Navigation principale', $fragment->getContent());
    }

    public function test_filtre_par_statut(): void
    {
        Categorie::factory()->create(['nom' => 'Robinetterie']);
        Categorie::factory()->inactif()->create(['nom' => 'Ancienne gamme']);

        $this->get(route('categories.index', ['statut' => 'inactives']))
            ->assertSee('Ancienne gamme')
            ->assertDontSee('Robinetterie');

        $this->get(route('categories.index', ['statut' => 'actives']))
            ->assertSee('Robinetterie')
            ->assertDontSee('Ancienne gamme');
    }

    public function test_pagination_par_douze(): void
    {
        Categorie::factory()->count(14)->create();

        $this->get(route('categories.index'))->assertSee('aria-label="Pagination"', false);
        $this->assertCount(2, $this->get(route('categories.index', ['page' => 2]))->viewData('categories')->items());
    }

    public function test_etat_vide_avec_bouton_ajouter(): void
    {
        $this->get(route('categories.index'))
            ->assertSee('Aucune catégorie')
            ->assertSee('Ajouter une catégorie');

        $this->get(route('categories.index', ['recherche' => 'introuvable']))
            ->assertSee('Aucune catégorie trouvée')
            ->assertSee('Effacer la recherche');
    }

    public function test_creation(): void
    {
        $this->post(route('categories.store'), ['nom' => '  Quincaillerie générale ', 'description' => 'Divers', 'actif' => '1'])
            ->assertRedirect(route('categories.index'))
            ->assertSessionHas('succes', 'Catégorie « Quincaillerie générale » créée.');

        $this->assertDatabaseHas('categories', ['nom' => 'Quincaillerie générale', 'actif' => true]);
    }

    public function test_validation_en_francais_et_reouverture_de_la_modale(): void
    {
        Categorie::factory()->create(['nom' => 'Plomberie']);

        $this->from(route('categories.index'))
            ->post(route('categories.store'), ['nom' => '', '_formulaire' => 'nouveau'])
            ->assertSessionHasErrors(['nom' => 'Le champ nom est obligatoire.'])
            ->assertSessionHasInput('_formulaire', 'nouveau');

        $this->post(route('categories.store'), ['nom' => 'Plomberie'])
            ->assertSessionHasErrors(['nom' => 'Une catégorie porte déjà ce nom.']);

        // Après l'erreur, la page rouvre la modale automatiquement
        $this->withSession(['errors' => (new ViewErrorBag)->put('default', new MessageBag(['nom' => 'x'])), '_old_input' => ['_formulaire' => 'nouveau', 'nom' => 'Plomb']])
            ->get(route('categories.index'))
            ->assertSee("dispatch('ouvrir-modal', 'formulaire-categorie')", false);
    }

    public function test_modification(): void
    {
        $categorie = Categorie::factory()->create(['nom' => 'Plomberie']);

        $this->from(route('categories.index'))
            ->put(route('categories.update', $categorie), ['nom' => 'Plomberie et sanitaire', 'actif' => '1'])
            ->assertRedirect(route('categories.index'))
            ->assertSessionHas('succes');

        $this->assertSame('Plomberie et sanitaire', $categorie->fresh()->nom);
    }

    public function test_une_case_decochee_desactive(): void
    {
        $categorie = Categorie::factory()->create();

        $this->put(route('categories.update', $categorie), ['nom' => $categorie->nom, 'actif' => '0']);

        $this->assertFalse($categorie->fresh()->actif);
    }

    public function test_une_categorie_utilisee_ne_peut_pas_etre_supprimee(): void
    {
        $categorie = Categorie::factory()->create(['nom' => 'Plomberie']);
        Produit::factory()->count(2)->for($categorie)->create();

        $this->from(route('categories.index'))
            ->delete(route('categories.destroy', $categorie))
            ->assertRedirect(route('categories.index'))
            ->assertSessionHas('erreur', 'La catégorie « Plomberie » est utilisée par 2 produits : elle ne peut pas être supprimée. Désactivez-la plutôt.');

        $this->assertNotSoftDeleted($categorie);
        // Le bouton Supprimer n'est pas proposé pour une catégorie utilisée
        $this->get(route('categories.index'))->assertDontSee('supprimer-categorie-'.$categorie->id, false);
    }

    public function test_un_produit_supprime_bloque_aussi_la_suppression(): void
    {
        $categorie = Categorie::factory()->create();
        Produit::factory()->for($categorie)->create()->delete();

        $this->delete(route('categories.destroy', $categorie))->assertSessionHas('erreur');
        $this->assertNotSoftDeleted($categorie);
    }

    public function test_une_categorie_vide_est_supprimee_en_douceur_et_journalisee(): void
    {
        $categorie = Categorie::factory()->create(['nom' => 'Temporaire']);

        $this->delete(route('categories.destroy', $categorie))
            ->assertSessionHas('succes', 'Catégorie « Temporaire » supprimée.');

        $this->assertSoftDeleted($categorie);
        $this->assertDatabaseHas('journal_activites', [
            'action' => 'categorie.supprimee',
            'modele' => 'categorie',
            'modele_id' => $categorie->id,
        ]);
    }

    public function test_recreer_un_nom_supprime_restaure_la_categorie(): void
    {
        $categorie = Categorie::factory()->create(['nom' => 'Jardinage']);
        $categorie->delete();

        $this->post(route('categories.store'), ['nom' => 'Jardinage', 'description' => 'Retour en rayon'])
            ->assertSessionHas('succes', 'Catégorie « Jardinage » restaurée.');

        $this->assertNotSoftDeleted($categorie);
        $this->assertSame('Retour en rayon', $categorie->fresh()->description);
        $this->assertSame(1, Categorie::withTrashed()->where('nom', 'Jardinage')->count());
    }

    public function test_renommer_vers_le_nom_d_une_categorie_supprimee_est_refuse(): void
    {
        Categorie::factory()->create(['nom' => 'Ancienne'])->delete();
        $categorie = Categorie::factory()->create(['nom' => 'Nouvelle']);

        $this->put(route('categories.update', $categorie), ['nom' => 'Ancienne'])
            ->assertSessionHasErrors(['nom' => 'Une catégorie porte déjà ce nom.']);
    }

    public function test_desactivation_et_reactivation_journalisees(): void
    {
        $categorie = Categorie::factory()->create(['nom' => 'Plomberie']);
        Produit::factory()->for($categorie)->create();

        $this->patch(route('categories.statut', $categorie))->assertSessionHas('succes');
        $this->assertFalse($categorie->fresh()->actif);

        $this->patch(route('categories.statut', $categorie));
        $this->assertTrue($categorie->fresh()->actif);

        $this->assertDatabaseHas('journal_activites', ['action' => 'categorie.desactivee', 'modele_id' => $categorie->id]);
        $this->assertDatabaseHas('journal_activites', ['action' => 'categorie.reactivee', 'modele_id' => $categorie->id]);
    }

    public function test_droits_vendeur_et_magasinier_refuses(): void
    {
        $categorie = Categorie::factory()->create();

        foreach ([RoleSeeder::VENDEUR, RoleSeeder::MAGASINIER] as $role) {
            $this->actingAs($this->avecRole($role));

            $this->get(route('categories.index'))->assertForbidden();
            $this->post(route('categories.store'), ['nom' => 'Pirate'])->assertForbidden();
            $this->put(route('categories.update', $categorie), ['nom' => 'Pirate'])->assertForbidden();
            $this->patch(route('categories.statut', $categorie))->assertForbidden();
            $this->delete(route('categories.destroy', $categorie))->assertForbidden();
        }

        $this->assertDatabaseMissing('categories', ['nom' => 'Pirate']);
        $this->assertNotSoftDeleted($categorie);
    }

    public function test_l_administrateur_a_acces(): void
    {
        $this->actingAs($this->avecRole(Role::ADMINISTRATEUR))
            ->get(route('categories.index'))
            ->assertOk();
    }
}
