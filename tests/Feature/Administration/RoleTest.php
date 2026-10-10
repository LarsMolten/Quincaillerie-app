<?php

namespace Tests\Feature\Administration;

use App\Models\Droit;
use App\Models\JournalActivite;
use App\Models\Role;
use App\Models\Utilisateur;
use Database\Seeders\DroitSeeder;
use Database\Seeders\ParametreSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Administration > Rôles et droits : cartes, matrice des droits, rôles personnalisés,
 * Administrateur verrouillé, rôles livrés protégés.
 */
class RoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed([RoleSeeder::class, DroitSeeder::class, ParametreSeeder::class]);
        $this->actingAs(Utilisateur::factory()->create(['role_id' => $this->role(Role::ADMINISTRATEUR)->id]));
    }

    private function role(string $nom): Role
    {
        return Role::where('nom', $nom)->firstOrFail();
    }

    public function test_cartes_et_matrice(): void
    {
        $total = Droit::count();
        $vendeur = $this->role(RoleSeeder::VENDEUR);

        $this->get(route('roles.index'))->assertOk()
            ->assertSeeInOrder([Role::ADMINISTRATEUR, RoleSeeder::RESPONSABLE, RoleSeeder::VENDEUR, RoleSeeder::MAGASINIER])
            ->assertSee('Par défaut')->assertSee('Nouveau rôle')
            ->assertSee($total.'<span class="font-normal text-texte-doux">/'.$total.'</span>', false)
            ->assertSee('10 %');

        $this->get(route('roles.show', $vendeur))->assertOk()
            ->assertSee('Ventes')->assertSee('Accorder des remises')->assertSee('ventes.remise')
            ->assertSee('value="ventes.creer" data-module="ventes"'."\n".'                                           checked', false)
            ->assertSee('Enregistrer les droits');

        // Administrateur : tout coché, désactivé, sans enregistrement
        $this->get(route('roles.show', $this->role(Role::ADMINISTRATEUR)))->assertOk()
            ->assertSee('ils ne sont pas modifiables')->assertDontSee('Enregistrer les droits');

        $this->actingAs(Utilisateur::factory()->create(['role_id' => $this->role(RoleSeeder::RESPONSABLE)->id]))
            ->get(route('roles.index'))->assertForbidden();
    }

    public function test_enregistrement_des_droits_journalise(): void
    {
        $vendeur = $this->role(RoleSeeder::VENDEUR);

        $this->put(route('roles.droits', $vendeur), ['droits' => ['ventes.voir', 'ventes.creer', 'ventes.remise', 'clients.gerer', 'factures.voir', 'paiements.gerer']])
            ->assertSessionHas('succes', 'Droits du rôle « Vendeur/Caissier » enregistrés : 1 ajouté(s), 1 retiré(s).');

        $this->assertEqualsCanonicalizing(['ventes.voir', 'ventes.creer', 'ventes.remise', 'clients.gerer', 'factures.voir', 'paiements.gerer'], $vendeur->droits()->pluck('code')->all());
        $journal = JournalActivite::where('action', 'role.droits_modifies')->firstOrFail();
        $this->assertSame(['ventes.remise'], $journal->details['ajoutes']);
        $this->assertSame(['produits.voir'], $journal->details['retires']);

        // Un compte du rôle reçoit aussitôt le nouveau droit
        $caissier = Utilisateur::factory()->create(['role_id' => $vendeur->id]);
        $this->assertTrue($caissier->fresh()->can('ventes.remise'));
        $this->assertFalse($caissier->fresh()->can('produits.voir'));

        // Sans changement : simple information, rien au journal
        $this->put(route('roles.droits', $vendeur), ['droits' => $vendeur->droits()->pluck('code')->all()])->assertSessionHas('info');
        $this->assertSame(1, JournalActivite::where('action', 'role.droits_modifies')->count());

        // Droit inconnu refusé ; Administrateur non modifiable
        $this->put(route('roles.droits', $vendeur), ['droits' => ['pirate.tout']])->assertSessionHasErrors('droits.0');
        $this->put(route('roles.droits', $this->role(Role::ADMINISTRATEUR)), ['droits' => []])
            ->assertSessionHas('erreur', 'L\'Administrateur a toujours tous les droits : ils ne sont pas modifiables.');
        $this->assertSame(Droit::count(), $this->role(Role::ADMINISTRATEUR)->droits()->count());
    }

    public function test_role_personnalise_cree_depuis_un_modele_modifie_et_supprime(): void
    {
        $this->post(route('roles.store'), ['nom' => 'Comptable', 'description' => 'Suit les finances', 'modele_id' => $this->role(RoleSeeder::MAGASINIER)->id])
            ->assertSessionHas('succes', 'Rôle « Comptable » créé : choisissez maintenant ses droits.');
        $comptable = $this->role('Comptable');
        $this->assertEqualsCanonicalizing($this->role(RoleSeeder::MAGASINIER)->droits()->pluck('code')->all(), $comptable->droits()->pluck('code')->all());
        $this->assertDatabaseHas('journal_activites', ['action' => 'role.cree', 'modele_id' => $comptable->id]);

        $this->post(route('roles.store'), ['nom' => 'Comptable'])->assertSessionHasErrors(['nom' => 'Un rôle porte déjà ce nom.']);

        $this->put(route('roles.update', $comptable), ['nom' => 'Comptabilité', 'description' => ''])->assertSessionHas('succes');
        $this->assertSame('Comptabilité', $comptable->fresh()->nom);

        // Supprimable tant qu'aucun compte (même supprimé) ne l'a
        $ancien = Utilisateur::factory()->create(['role_id' => $comptable->id]);
        $ancien->delete();
        $this->delete(route('roles.destroy', $comptable))
            ->assertSessionHas('erreur', 'Le rôle « Comptabilité » est attribué à 1 compte (actifs, désactivés ou supprimés) : il ne peut pas être supprimé.');
        $ancien->forceDelete();

        $this->delete(route('roles.destroy', $comptable))->assertRedirect(route('roles.index'))->assertSessionHas('succes', 'Rôle « Comptabilité » supprimé.');
        $this->assertDatabaseMissing('roles', ['id' => $comptable->id]);
        $this->assertDatabaseMissing('role_droit', ['role_id' => $comptable->id]);
        $this->assertDatabaseHas('journal_activites', ['action' => 'role.supprime', 'modele_id' => $comptable->id]);
    }

    public function test_roles_livres_proteges(): void
    {
        $magasinier = $this->role(RoleSeeder::MAGASINIER);

        $this->put(route('roles.update', $magasinier), ['nom' => 'Stockiste', 'description' => 'Nouveau texte'])
            ->assertSessionHas('erreur', 'Le rôle « Magasinier » est livré avec l\'application : il ne peut pas être renommé.');
        $this->put(route('roles.update', $magasinier), ['nom' => 'Magasinier', 'description' => 'Nouveau texte'])->assertSessionHas('succes');
        $this->assertSame('Nouveau texte', $magasinier->fresh()->description);

        $this->delete(route('roles.destroy', $magasinier))
            ->assertSessionHas('erreur', 'Le rôle « Magasinier » est livré avec l\'application : il ne peut pas être supprimé.');
        $this->assertDatabaseHas('roles', ['nom' => 'Magasinier']);
    }
}
