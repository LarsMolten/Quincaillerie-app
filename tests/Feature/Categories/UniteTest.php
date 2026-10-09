<?php

namespace Tests\Feature\Categories;

use App\Models\Produit;
use App\Models\Role;
use App\Models\Unite;
use App\Models\Utilisateur;
use Database\Seeders\DroitSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UniteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed([RoleSeeder::class, DroitSeeder::class]);
        $this->actingAs(Utilisateur::factory()->create([
            'role_id' => Role::where('nom', RoleSeeder::RESPONSABLE)->firstOrFail()->id,
        ]));
    }

    public function test_la_liste_et_le_tri_par_nombre_de_produits(): void
    {
        $kg = Unite::factory()->create(['nom' => 'kilogramme', 'abreviation' => 'kg']);
        Unite::factory()->create(['nom' => 'mètre', 'abreviation' => 'm']);
        Produit::factory()->count(2)->for($kg)->create();

        $this->get(route('unites.index'))
            ->assertOk()
            ->assertSee('kilogramme')
            ->assertSee('Unités de vente')
            ->assertSee('data-libelle="Produits"', false);

        $this->get(route('unites.index', ['tri' => 'produits', 'ordre' => 'desc']))
            ->assertSeeInOrder(['kilogramme', 'mètre']);
        $this->get(route('unites.index', ['tri' => 'produits', 'ordre' => 'asc']))
            ->assertSeeInOrder(['mètre', 'kilogramme']);
    }

    public function test_recherche_par_abreviation_et_fragment(): void
    {
        Unite::factory()->create(['nom' => 'kilogramme', 'abreviation' => 'kg']);
        Unite::factory()->create(['nom' => 'litre', 'abreviation' => 'L']);

        $reponse = $this->get(route('unites.index', ['recherche' => 'kg']), ['X-Fragment' => 'liste'])
            ->assertSee('kilogramme')
            ->assertDontSee('litre');

        $this->assertStringNotContainsString('<!DOCTYPE', $reponse->getContent());
    }

    public function test_etat_vide(): void
    {
        $this->get(route('unites.index'))->assertSee('Aucune unité')->assertSee('Ajouter une unité');
    }

    public function test_creation_et_modification(): void
    {
        $this->post(route('unites.store'), ['nom' => 'rouleau', 'abreviation' => 'rlx'])
            ->assertSessionHas('succes', 'Unité « rouleau » créée.');

        $unite = Unite::where('nom', 'rouleau')->firstOrFail();

        $this->put(route('unites.update', $unite), ['nom' => 'rouleau', 'abreviation' => 'rl'])
            ->assertSessionHas('succes', 'Unité « rouleau » modifiée.');

        $this->assertSame('rl', $unite->fresh()->abreviation);
    }

    public function test_nom_et_abreviation_uniques_en_francais(): void
    {
        Unite::factory()->create(['nom' => 'kilogramme', 'abreviation' => 'kg']);

        $this->post(route('unites.store'), ['nom' => 'kilogramme', 'abreviation' => 'kg'])
            ->assertSessionHasErrors([
                'nom' => 'Une unité porte déjà ce nom.',
                'abreviation' => 'Une unité utilise déjà cette abréviation.',
            ]);

        $this->post(route('unites.store'), [])
            ->assertSessionHasErrors([
                'nom' => 'Le champ nom est obligatoire.',
                'abreviation' => 'Le champ abréviation est obligatoire.',
            ]);
    }

    public function test_une_unite_utilisee_est_refusee(): void
    {
        $unite = Unite::factory()->create(['nom' => 'sac', 'abreviation' => 'sac']);
        Produit::factory()->for($unite)->create();

        $this->from(route('unites.index'))
            ->delete(route('unites.destroy', $unite))
            ->assertRedirect(route('unites.index'))
            ->assertSessionHas('erreur', 'L\'unité « sac » est utilisée par 1 produit : elle ne peut pas être supprimée. Modifiez d\'abord ces produits.');

        $this->assertNotSoftDeleted($unite);
    }

    public function test_une_unite_inutilisee_est_supprimee_en_douceur_puis_restaurable(): void
    {
        $unite = Unite::factory()->create(['nom' => 'gallon', 'abreviation' => 'gal']);

        $this->delete(route('unites.destroy', $unite))->assertSessionHas('succes', 'Unité « gallon » supprimée.');
        $this->assertSoftDeleted($unite);
        $this->assertDatabaseHas('journal_activites', ['action' => 'unite.supprimee', 'modele_id' => $unite->id]);

        $this->post(route('unites.store'), ['nom' => 'gallon', 'abreviation' => 'gal'])
            ->assertSessionHas('succes', 'Unité « gallon » restaurée.');
        $this->assertNotSoftDeleted($unite);
    }

    public function test_conflit_entre_deux_unites_supprimees(): void
    {
        Unite::factory()->create(['nom' => 'gallon', 'abreviation' => 'gal'])->delete();
        Unite::factory()->create(['nom' => 'pinte', 'abreviation' => 'pt'])->delete();

        $this->post(route('unites.store'), ['nom' => 'gallon', 'abreviation' => 'pt'])
            ->assertSessionHasErrors('nom');
    }

    public function test_droits(): void
    {
        $unite = Unite::factory()->create();
        $this->actingAs(Utilisateur::factory()->create([
            'role_id' => Role::where('nom', RoleSeeder::VENDEUR)->firstOrFail()->id,
        ]));

        $this->get(route('unites.index'))->assertForbidden();
        $this->post(route('unites.store'), ['nom' => 'x', 'abreviation' => 'x'])->assertForbidden();
        $this->put(route('unites.update', $unite), ['nom' => 'x', 'abreviation' => 'x'])->assertForbidden();
        $this->delete(route('unites.destroy', $unite))->assertForbidden();
    }
}
