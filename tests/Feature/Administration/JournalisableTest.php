<?php

namespace Tests\Feature\Administration;

use App\Enums\TypeMouvementStock;
use App\Models\Categorie;
use App\Models\Client;
use App\Models\JournalActivite;
use App\Models\Parametre;
use App\Models\Produit;
use App\Models\Role;
use App\Models\Utilisateur;
use App\Services\JournalService;
use App\Services\MouvementStockService;
use Database\Seeders\DroitSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/**
 * Trait Journalisable : créations, modifications, suppressions et restaurations tracées automatiquement
 * (anciennes et nouvelles valeurs, auteur, IP) ; journal non modifiable.
 */
class JournalisableTest extends TestCase
{
    use RefreshDatabase;

    private Utilisateur $auteur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, DroitSeeder::class]);
        $this->auteur = Utilisateur::factory()->create(['nom' => 'Rabe Admin', 'role_id' => Role::where('nom', Role::ADMINISTRATEUR)->value('id')]);
        $this->actingAs($this->auteur);
    }

    private function derniere(string $action): JournalActivite
    {
        return JournalActivite::where('action', $action)->latest('id')->firstOrFail();
    }

    public function test_creation_modification_suppression_et_restauration(): void
    {
        $client = Client::factory()->create(['nom' => 'Rakoto BTP', 'telephone' => '0340000000']);
        $creation = $this->derniere('client.cree');
        $this->assertSame($this->auteur->id, $creation->utilisateur_id);
        $this->assertSame(['client', $client->id], [$creation->modele, $creation->modele_id]);
        $this->assertSame('Rakoto BTP', $creation->details['objet']);
        $this->assertSame('0340000000', $creation->details['nouvelles']['telephone']);
        $this->assertSame('127.0.0.1', $creation->adresse_ip);

        $client->update(['telephone' => '0331111111', 'nom' => 'Rakoto BTP']);
        $modification = $this->derniere('client.modifie');
        $this->assertSame(['telephone' => '0340000000'], $modification->details['anciennes']);
        $this->assertSame(['telephone' => '0331111111'], $modification->details['nouvelles'], 'Seuls les champs réellement changés.');

        $client->update(['actif' => false]);
        $this->assertSame([false], array_values($this->derniere('client.desactive')->details['nouvelles']));
        $client->update(['actif' => true]);
        $this->derniere('client.reactive');

        $client->delete();
        $this->assertSame('0331111111', $this->derniere('client.supprime')->details['anciennes']['telephone'], 'Instantané à la suppression.');
        $client->restore();
        $this->derniere('client.restaure');

        // Accord au féminin
        $categorie = Categorie::factory()->create(['nom' => 'Jardinage']);
        $categorie->delete();
        $this->assertSame('Jardinage', $this->derniere('categorie.supprimee')->details['objet']);
    }

    public function test_attributs_ignores_masques_et_evenements_limites(): void
    {
        // Le stock, modifié à chaque mouvement, n'encombre pas le journal (il a sa propre table)
        $produit = Produit::factory()->create(['stock_actuel' => 0]);
        app(MouvementStockService::class)->enregistrer($produit, TypeMouvementStock::Achat, 10);
        $this->assertEquals(10, $produit->fresh()->stock_actuel);
        $this->assertSame(0, JournalActivite::where('action', 'produit.modifie')->count());

        // Mot de passe jamais en clair ; préférence de thème ignorée
        $compte = Utilisateur::factory()->create(['password' => 'Secret123']);
        $this->assertSame('(modifié)', $this->derniere('utilisateur.cree')->details['nouvelles']['password']);
        $this->assertStringNotContainsString('Secret123', json_encode(JournalActivite::all()->pluck('details')));
        $compte->update(['preference_theme' => 'sombre']);
        $this->assertSame(0, JournalActivite::where('action', 'utilisateur.modifie')->count());
        $compte->update(['password' => 'Autre4567']);
        $this->assertSame(['password' => '(modifié)'], $this->derniere('utilisateur.modifie')->details['nouvelles']);

        // Paramètre : clé comme objet, ancienne et nouvelle valeur
        Parametre::create(['cle' => 'taux_tva', 'valeur' => '0'])->update(['valeur' => '20']);
        $parametre = $this->derniere('parametre.modifie');
        $this->assertSame(['taux_tva', '0', '20'], [$parametre->details['objet'], $parametre->details['anciennes']['valeur'], $parametre->details['nouvelles']['valeur']]);
    }

    public function test_journal_non_modifiable_et_suspension_pour_les_seeders(): void
    {
        $entree = app(JournalService::class)->enregistrer('connexion', $this->auteur);

        $this->assertThrows(fn () => $entree->update(['action' => 'autre']), LogicException::class);
        $this->assertThrows(fn () => $entree->delete(), LogicException::class);
        $this->assertSame('connexion', $entree->fresh()->action);

        $avant = JournalActivite::count();
        JournalService::sansJournal(fn () => Client::factory()->count(3)->create());
        $this->assertSame($avant, JournalActivite::count());
        Client::factory()->create();
        $this->assertSame($avant + 1, JournalActivite::count(), 'La journalisation reprend après.');
    }
}
