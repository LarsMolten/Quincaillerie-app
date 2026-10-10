<?php

namespace Tests\Feature\Paiements;

use App\Models\Achat;
use App\Models\Fournisseur;
use App\Models\Role;
use App\Models\Utilisateur;
use Database\Seeders\DroitSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DettesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-10 10:00:00');
        $this->withoutVite();
        $this->seed([RoleSeeder::class, DroitSeeder::class]);
        $this->actingAs($this->avecRole(RoleSeeder::RESPONSABLE));
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

    private function achatImpaye(string $fournisseur, int $reste, int $joursAvant): Achat
    {
        return Achat::factory()
            ->for(Fournisseur::where('nom', $fournisseur)->first() ?? Fournisseur::factory()->create(['nom' => $fournisseur]))
            ->nonSolde($reste)
            ->create(['date_achat' => now()->subDays($joursAvant)]);
    }

    public function test_liste_triee_par_montant_avec_anciennete_et_filtres(): void
    {
        $this->achatImpaye('Holcim Madagascar', 900000, 10);
        $this->achatImpaye('Quincaillerie Gros', 400000, 75);
        Achat::factory()->for(Fournisseur::factory()->create(['nom' => 'Fournisseur Paye']))->create();
        Achat::factory()->for(Fournisseur::factory()->create(['nom' => 'Achat Annule']))->nonSolde(50000)->annule()->create();

        $this->get(route('dettes.index'))
            ->assertOk()
            ->assertSeeInOrder(['Holcim Madagascar', 'Quincaillerie Gros'])
            ->assertDontSee('Fournisseur Paye')
            ->assertDontSee('Achat Annule')
            ->assertSee("1\u{00A0}300\u{00A0}000\u{00A0}Ar", false)
            ->assertSee('il y a 75 jour(s)', false);

        $this->get(route('dettes.index', ['anciennete' => 'ancienne']))->assertSee('Quincaillerie Gros')->assertDontSee('Holcim Madagascar');
        $this->get(route('dettes.index', ['recherche' => 'holcim']))->assertSee('Holcim Madagascar')->assertDontSee('Quincaillerie Gros');
    }

    public function test_detail_et_reglement_depuis_la_modale(): void
    {
        $achat = $this->achatImpaye('Holcim Madagascar', 300000, 20);
        $fournisseur = $achat->fournisseur;

        $detail = $this->get(route('dettes.show', $fournisseur))->assertOk()->assertSee($achat->numero)->assertSee('Régler');
        $this->assertStringContainsString(route('achats.paiements.store', $achat), str_replace('\\', '', $detail->getContent()));

        $this->postJson(route('achats.paiements.store', $achat), ['montant' => '300000', 'mode' => 'virement', 'reference' => 'VIR-12', 'date_paiement' => today()->toDateString()])
            ->assertOk()
            ->assertJsonPath('numero', 'REC-2026-00001')
            ->assertJsonPath('reste', 0);

        $this->assertSame(0.0, (float) $achat->fresh()->reste_a_payer);
        $this->assertDatabaseHas('journal_activites', ['action' => 'paiement.enregistre', 'modele' => 'paiement']);
        $this->get(route('dettes.index'))->assertSee('Aucune dette fournisseur');
        $this->get(route('dettes.show', $fournisseur))->assertSee('Plus aucun achat impayé');
    }

    public function test_droits(): void
    {
        $fournisseur = $this->achatImpaye('Holcim Madagascar', 300000, 20)->fournisseur;

        foreach ([RoleSeeder::VENDEUR, RoleSeeder::MAGASINIER] as $role) {
            $this->actingAs($this->avecRole($role));
            $this->get(route('dettes.index'))->assertForbidden();
            $this->get(route('dettes.show', $fournisseur))->assertForbidden();
        }
    }
}
