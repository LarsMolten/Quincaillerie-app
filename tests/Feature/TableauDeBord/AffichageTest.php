<?php

namespace Tests\Feature\TableauDeBord;

use App\Enums\ModePaiement;
use App\Enums\TypeMouvementStock;
use App\Models\Client;
use App\Models\Produit;
use App\Models\Role;
use App\Models\Utilisateur;
use App\Services\MouvementStockService;
use App\Services\VenteService;
use Database\Seeders\ClientComptoirSeeder;
use Database\Seeders\DroitSeeder;
use Database\Seeders\ParametreSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AffichageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-12 15:00:00');
        Cache::flush();
        $this->withoutVite();
        $this->seed([RoleSeeder::class, DroitSeeder::class, ParametreSeeder::class, ClientComptoirSeeder::class]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function avecRole(string $role, string $nom = 'Rakoto Hery'): Utilisateur
    {
        return Utilisateur::factory()->create(['nom' => $nom, 'role_id' => Role::where('nom', $role)->firstOrFail()->id]);
    }

    public function test_l_administrateur_voit_tout(): void
    {
        $this->actingAs($this->avecRole(Role::ADMINISTRATEUR));

        $page = $this->get(route('accueil'))->assertOk()
            ->assertSee('Bonjour, Hery')
            ->assertSee('Lundi 12 octobre 2026')
            ->assertSee('Nouvelle vente')
            ->assertSeeInOrder(['Ventes du jour', 'Achats du jour', 'Bénéfice du jour', 'Produits actifs', 'Clients', 'Fournisseurs'])
            ->assertSee('graphiqueVentes', false);

        // Adresses des cartes différées (JSON échappé dans les attributs)
        $html = str_replace('\\', '', $page->getContent());
        foreach (['stock', 'top', 'creances', 'dernieres'] as $carte) {
            $this->assertStringContainsString(route('tableau-de-bord.carte', $carte), $html);
        }
    }

    public function test_le_vendeur_ne_voit_ni_achats_ni_benefice(): void
    {
        $this->actingAs($this->avecRole(RoleSeeder::VENDEUR));

        $this->get(route('accueil'))->assertOk()
            ->assertSee('Ventes du jour')
            ->assertDontSee('Achats du jour')
            ->assertDontSee('Bénéfice du jour')
            ->assertDontSee('Fournisseurs')
            ->assertSee('Nouvelle vente');

        $this->getJson(route('tableau-de-bord.carte', ['ventes', 'jours' => 7]))->assertOk()->assertJsonCount(7, 'valeurs');
        $this->get(route('tableau-de-bord.carte', 'creances'))->assertOk();
    }

    public function test_le_magasinier_voit_le_stock_sans_les_ventes(): void
    {
        $this->actingAs($this->avecRole(RoleSeeder::MAGASINIER));

        $page = $this->get(route('accueil'))->assertOk()
            ->assertSee('Produits actifs')
            ->assertDontSee('Ventes du jour')
            ->assertDontSee('Bénéfice du jour')
            ->assertDontSee('graphiqueVentes', false)
            ->assertDontSee('Nouvelle vente');
        $this->assertStringContainsString(route('tableau-de-bord.carte', 'stock'), str_replace('\\', '', $page->getContent()));

        $this->getJson(route('tableau-de-bord.carte', 'ventes'))->assertForbidden();
        $this->get(route('tableau-de-bord.carte', 'dernieres'))->assertForbidden();
        $this->get(route('tableau-de-bord.carte', 'creances'))->assertForbidden();
        $this->get(route('tableau-de-bord.carte', 'stock'))->assertOk();
    }

    public function test_fragments_des_cartes(): void
    {
        $this->actingAs($this->avecRole(Role::ADMINISTRATEUR));
        $produit = Produit::factory()->create(['nom' => 'Ciment 50 kg', 'stock_actuel' => 0, 'stock_minimum' => 10, 'prix_vente' => 40000, 'actif' => true]);
        app(MouvementStockService::class)->enregistrer($produit, TypeMouvementStock::AjustementPositif, 6);
        $vente = app(VenteService::class)->creer(Client::where('nom', Client::COMPTOIR)->firstOrFail(), [['produit_id' => $produit->id, 'quantite' => 2]], 0, ModePaiement::Especes, 100000);

        $stock = $this->get(route('tableau-de-bord.carte', 'stock'))->assertOk()->assertSee('Ciment 50 kg')->assertSee('aria-valuenow="40"', false);
        $this->assertStringNotContainsString('<!DOCTYPE', $stock->getContent());
        $this->get(route('tableau-de-bord.carte', 'top'))->assertSee('Ciment 50 kg')->assertSee("80\u{00A0}000\u{00A0}Ar", false);
        $this->get(route('tableau-de-bord.carte', 'dernieres'))->assertSee($vente->facture->numero)->assertSee('Payé');
        $this->get(route('tableau-de-bord.carte', 'creances'))->assertSee('Aucune créance');
        $this->getJson(route('tableau-de-bord.carte', ['ventes', 'jours' => 90]))->assertOk()->assertJsonPath('jours', 90)->assertJsonPath('total', 80000);
        $this->get('/tableau-de-bord/inconnue')->assertNotFound();
    }

    public function test_visiteur_redirige(): void
    {
        $this->get(route('accueil'))->assertRedirect(route('connexion'));
        $this->get(route('tableau-de-bord.carte', 'stock'))->assertRedirect(route('connexion'));
    }
}
