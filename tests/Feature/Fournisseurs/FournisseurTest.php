<?php

namespace Tests\Feature\Fournisseurs;

use App\Models\Achat;
use App\Models\Fournisseur;
use App\Models\Paiement;
use App\Models\Role;
use App\Models\Utilisateur;
use Database\Seeders\DroitSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FournisseurTest extends TestCase
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

    public function test_liste_recherche_et_avatar(): void
    {
        Fournisseur::factory()->create(['nom' => 'Ravinala Matériaux', 'telephone' => '020 22 214 56']);
        Fournisseur::factory()->create(['nom' => 'Océan Indien Électricité', 'telephone' => '032 05 774 19']);

        $this->get(route('fournisseurs.index'))
            ->assertOk()
            ->assertSee('Ravinala Matériaux')
            ->assertSee('>RM<', false);

        $this->get(route('fournisseurs.index', ['recherche' => '214 56']))
            ->assertSee('Ravinala Matériaux')
            ->assertDontSee('Océan Indien');

        $fragment = $this->get(route('fournisseurs.index', ['recherche' => 'ravinala']), ['X-Fragment' => 'liste'])->getContent();
        $this->assertStringNotContainsString('<!DOCTYPE', $fragment);
    }

    public function test_puces_de_statut_et_etat_vide(): void
    {
        Fournisseur::factory()->create(['nom' => 'Fournisseur actif']);
        Fournisseur::factory()->inactif()->create(['nom' => 'Ancien partenaire']);

        $this->get(route('fournisseurs.index'))->assertSee('Fournisseur actif')->assertDontSee('Ancien partenaire');
        $this->get(route('fournisseurs.index', ['statut' => 'inactifs']))->assertSee('Ancien partenaire')->assertDontSee('Fournisseur actif');
        $this->get(route('fournisseurs.index', ['statut' => 'tous']))->assertSee('Ancien partenaire')->assertSee('Fournisseur actif');
        $this->get(route('fournisseurs.index', ['recherche' => 'introuvable']))->assertSee('Aucun fournisseur trouvé');
    }

    public function test_tri_par_dette(): void
    {
        Achat::factory()->for(Fournisseur::factory()->create(['nom' => 'Petite dette']))->nonSolde(10000)->create();
        Achat::factory()->for(Fournisseur::factory()->create(['nom' => 'Grosse dette']))->nonSolde(900000)->create();

        $this->get(route('fournisseurs.index', ['tri' => 'dette', 'ordre' => 'desc']))->assertSeeInOrder(['Grosse dette', 'Petite dette']);
    }

    public function test_etat_vide_initial(): void
    {
        $this->get(route('fournisseurs.index'))->assertSee('Aucun fournisseur')->assertSee('Ajouter un fournisseur');
    }

    public function test_creation_et_validation_en_francais(): void
    {
        $this->post(route('fournisseurs.store'), ['nom' => 'Betsiboka Fers', 'telephone' => '+261 20 22 331 08', 'email' => 'contact@betsiboka.mg'])
            ->assertRedirect(route('fournisseurs.index'))
            ->assertSessionHas('succes', 'Fournisseur « Betsiboka Fers » créé.');

        $this->post(route('fournisseurs.store'), ['nom' => '', 'telephone' => '', 'email' => 'pas-un-email', '_formulaire' => 'nouveau'])
            ->assertSessionHasErrors([
                'nom' => 'Le champ nom est obligatoire.',
                'telephone' => 'Le champ téléphone est obligatoire.',
                'email',
            ])
            ->assertSessionHasInput('_formulaire', 'nouveau');
    }

    public function test_modification(): void
    {
        $fournisseur = Fournisseur::factory()->create();

        $this->put(route('fournisseurs.update', $fournisseur), ['nom' => 'Nouveau nom', 'telephone' => '034 11 452 77'])
            ->assertSessionHas('succes');

        $this->assertSame('Nouveau nom', $fournisseur->fresh()->nom);
    }

    public function test_desactivation_journalisee(): void
    {
        $fournisseur = Fournisseur::factory()->create();

        $this->patch(route('fournisseurs.statut', $fournisseur))->assertSessionHas('succes');
        $this->assertFalse($fournisseur->fresh()->actif);
        $this->assertDatabaseHas('journal_activites', ['action' => 'fournisseur.desactive', 'modele' => 'fournisseur', 'modele_id' => $fournisseur->id]);
    }

    public function test_suppression_refusee_avec_achats_et_douce_sinon(): void
    {
        $avecAchat = Fournisseur::factory()->create(['nom' => 'Holcim']);
        Achat::factory()->for($avecAchat)->create();

        $this->delete(route('fournisseurs.destroy', $avecAchat))
            ->assertSessionHas('erreur', 'Le fournisseur « Holcim » a des achats enregistrés : il ne peut pas être supprimé. Désactivez-le plutôt.');
        $this->assertNotSoftDeleted($avecAchat);

        $sansAchat = Fournisseur::factory()->create();
        $this->delete(route('fournisseurs.destroy', $sansAchat))->assertRedirect(route('fournisseurs.index'));
        $this->assertSoftDeleted($sansAchat);
        $this->assertDatabaseHas('journal_activites', ['action' => 'fournisseur.supprime', 'modele_id' => $sansAchat->id]);
    }

    public function test_fiche_dette_achats_et_paiements(): void
    {
        $fournisseur = Fournisseur::factory()->create(['nom' => 'Ravinala Matériaux']);
        $achat = Achat::factory()->for($fournisseur)->nonSolde(120000)->create(['numero' => 'ACH-2026-00011']);
        Achat::factory()->for($fournisseur)->nonSolde(30000)->create();
        Achat::factory()->for($fournisseur)->nonSolde(999000)->annule()->create(); // ignoré
        Paiement::factory()->pourAchat()->create(['payable_id' => $achat->id, 'montant' => 50000]);

        $this->get(route('fournisseurs.show', $fournisseur))
            ->assertOk()
            ->assertSee('Dette totale envers ce fournisseur')
            ->assertSee("150\u{00A0}000\u{00A0}Ar", false)
            ->assertSee('ACH-2026-00011')
            ->assertSee('Non soldé')
            ->assertSee('Paiements versés')
            ->assertSee("50\u{00A0}000\u{00A0}Ar", false);

        $this->assertSame(150000.0, $fournisseur->dette_totale);
    }

    public function test_droits(): void
    {
        $fournisseur = Fournisseur::factory()->create();

        foreach ([RoleSeeder::VENDEUR, RoleSeeder::MAGASINIER] as $role) {
            $this->actingAs($this->avecRole($role));
            $this->get(route('fournisseurs.index'))->assertForbidden();
            $this->get(route('fournisseurs.show', $fournisseur))->assertForbidden();
            $this->post(route('fournisseurs.store'), ['nom' => 'x', 'telephone' => '0340000000'])->assertForbidden();
            $this->patch(route('fournisseurs.statut', $fournisseur))->assertForbidden();
            $this->delete(route('fournisseurs.destroy', $fournisseur))->assertForbidden();
        }
    }
}
