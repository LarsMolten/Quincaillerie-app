<?php

namespace Tests\Feature\Administration;

use App\Models\Client;
use App\Models\JournalActivite;
use App\Models\Role;
use App\Models\Utilisateur;
use App\Services\JournalService;
use App\Support\LibelleJournal;
use Database\Seeders\DroitSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Administration > Journal d'activité : chronologie en phrases, filtres, détail des valeurs, lecture seule.
 */
class JournalPageTest extends TestCase
{
    use RefreshDatabase;

    private Utilisateur $admin;

    private Utilisateur $vendeur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed([RoleSeeder::class, DroitSeeder::class]);
        Carbon::setTestNow('2026-10-12 10:00:00');

        $this->admin = Utilisateur::factory()->create(['nom' => 'Rabe Admin', 'role_id' => Role::where('nom', Role::ADMINISTRATEUR)->value('id')]);
        $this->vendeur = Utilisateur::factory()->create(['nom' => 'Hery Vendeur', 'role_id' => Role::where('nom', RoleSeeder::VENDEUR)->value('id')]);

        // Activité connue : connexion du vendeur (hier), création et modification d'un client par l'administrateur (aujourd'hui)
        Carbon::setTestNow('2026-10-11 08:15:00');
        app(JournalService::class)->enregistrer('connexion', $this->vendeur, [], $this->vendeur);
        Carbon::setTestNow('2026-10-12 09:30:00');
        $this->actingAs($this->admin);
        $client = Client::factory()->create(['nom' => 'Rakoto BTP', 'telephone' => '0340000000']);
        $client->update(['telephone' => '0331111111']);
        app(JournalService::class)->enregistrer('vente.annule', null, ['objet' => 'VTE-2026-00007', 'motif' => 'Erreur de caisse']);
        Carbon::setTestNow('2026-10-12 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_chronologie_en_phrases_avec_detail(): void
    {
        $this->get(route('journal.index'))->assertOk()
            ->assertSeeInOrder(['Aujourd&#039;hui', 'Rabe Admin', 'a annulé', 'la vente « VTE-2026-00007 »', 'a modifié', 'le client « Rakoto BTP »', 'a créé', 'Hier', 'Hery Vendeur', 's&#039;est connecté(e)'], false)
            ->assertSee('Erreur de caisse')
            ->assertSeeInOrder(['Téléphone', '0340000000', '0331111111'])
            ->assertSee('Vendeur/Caissier')
            ->assertSee('09:30');
    }

    public function test_filtres(): void
    {
        $fragment = ['X-Fragment' => 'liste'];

        $this->get(route('journal.index', ['utilisateur' => $this->vendeur->id]), $fragment)
            ->assertSee('s&#039;est connecté(e)', false)->assertDontSee('Rakoto BTP')->assertDontSee('<html', false);
        $this->get(route('journal.index', ['module' => 'client']), $fragment)->assertSee('Rakoto BTP')->assertDontSee('VTE-2026-00007')->assertDontSee('connecté');
        $this->get(route('journal.index', ['type' => 'annulations']), $fragment)->assertSee('VTE-2026-00007')->assertDontSee('Rakoto BTP');
        $this->get(route('journal.index', ['type' => 'creations']), $fragment)->assertSee('a créé')->assertDontSee('a modifié');
        $this->get(route('journal.index', ['recherche' => 'Erreur de caisse']), $fragment)->assertSee('VTE-2026-00007')->assertDontSee('Rakoto BTP');
        $this->get(route('journal.index', ['periode' => 'aujourdhui']), $fragment)->assertSee('Rakoto BTP')->assertDontSee('connecté');
        $this->get(route('journal.index', ['periode' => 'mois_dernier']), $fragment)->assertSee('Aucune activité');
    }

    public function test_lecture_seule_et_droit(): void
    {
        $avant = JournalActivite::count();
        $this->post('/journal')->assertMethodNotAllowed();
        $this->delete('/journal')->assertMethodNotAllowed();
        $this->assertSame($avant, JournalActivite::count());

        $this->actingAs($this->vendeur)->get(route('journal.index'))->assertForbidden();
        $this->actingAs(Utilisateur::factory()->avecDroits(['journal.voir'])->create())->get(route('journal.index'))->assertOk();
    }

    public function test_libelles_des_actions(): void
    {
        $entree = fn (string $action, array $details = []) => new JournalActivite(['action' => $action, 'details' => $details]);

        $this->assertSame('a supprimé', LibelleJournal::presenter($entree('categorie.supprimee', ['objet' => 'Jardinage']))['verbe']);
        $this->assertSame('la catégorie « Jardinage »', LibelleJournal::presenter($entree('categorie.supprimee', ['objet' => 'Jardinage']))['objet']);
        $this->assertSame('a modifié les droits du', LibelleJournal::presenter($entree('role.droits_modifies', ['objet' => 'Vendeur']))['verbe']);
        $this->assertSame('rôle « Vendeur »', LibelleJournal::presenter($entree('role.droits_modifies', ['objet' => 'Vendeur']))['objet']);
        $this->assertSame('« Hery »', LibelleJournal::presenter($entree('utilisateur.mot_de_passe_reinitialise', ['objet' => 'Hery']))['objet']);
        $this->assertSame('a désactivé', LibelleJournal::presenter($entree('produit.desactive'))['verbe']);
        $this->assertSame('le paramètre « Couleur d\'accent »', LibelleJournal::presenter($entree('parametre.modifie', ['objet' => 'couleur_accent']))['objet']);
        $this->assertSame(['modifications', 'suppressions', 'annulations', 'connexions', 'creations'], [
            LibelleJournal::type('stock.ajuste'), LibelleJournal::type('depense.supprimee'), LibelleJournal::type('achat.annule'),
            LibelleJournal::type('connexion.refusee'), LibelleJournal::type('vente.creee'),
        ]);
        $this->assertSame('Oui', LibelleJournal::valeur(1, 'actif'));
    }
}
