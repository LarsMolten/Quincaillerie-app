<?php

namespace Tests\Feature\Paiements;

use App\Enums\ModePaiement;
use App\Models\Achat;
use App\Models\Client;
use App\Models\Fournisseur;
use App\Models\Role;
use App\Models\Utilisateur;
use App\Models\Vente;
use App\Services\PaiementService;
use Database\Seeders\ClientComptoirSeeder;
use Database\Seeders\DroitSeeder;
use Database\Seeders\ParametreSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PaiementHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-10 09:30:00');
        $this->withoutVite();
        $this->seed([RoleSeeder::class, DroitSeeder::class, ParametreSeeder::class, ClientComptoirSeeder::class]);
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

    private function venteDe(string $client, int $reste = 100000): Vente
    {
        return Vente::factory()->for(Client::factory()->create(['nom' => $client]))->create(['total' => $reste, 'montant_paye' => 0, 'reste_a_payer' => $reste]);
    }

    private function achatDe(string $fournisseur, int $reste = 100000): Achat
    {
        return Achat::factory()->for(Fournisseur::factory()->create(['nom' => $fournisseur]))->create(['total' => $reste, 'montant_paye' => 0, 'reste_a_payer' => $reste]);
    }

    public function test_historique_totaux_par_mode_filtres_et_recherche(): void
    {
        $service = app(PaiementService::class);
        $service->enregistrer($this->venteDe('Rakoto BTP'), 30000, ModePaiement::Especes);
        $service->enregistrer($this->venteDe('Rasoa Déco'), 20000, ModePaiement::MobileMoney, 'MVOLA-77');
        $service->enregistrer($this->achatDe('Holcim Madagascar'), 50000, ModePaiement::Virement);
        Carbon::setTestNow('2026-08-15 10:00:00');
        $service->enregistrer($this->venteDe('Ancien Client'), 10000, ModePaiement::Especes);
        Carbon::setTestNow('2026-10-10 09:30:00');

        // Ce mois (défaut) : totaux par mode, encaissements et décaissements
        $this->get(route('paiements.index'))
            ->assertOk()
            ->assertSee('REC-2026-00001')->assertSee('REC-2026-00003')
            ->assertDontSee('REC-2026-00004')
            ->assertSee("30\u{00A0}000\u{00A0}Ar", false)
            ->assertSee("décaissé 50\u{00A0}000\u{00A0}Ar", false)
            ->assertSee('Holcim Madagascar');

        $this->get(route('paiements.index', ['periode' => 'tout']))->assertSee('REC-2026-00004');
        $this->get(route('paiements.index', ['mode' => 'mobile_money']))->assertSee('Rasoa Déco')->assertDontSee('Rakoto BTP');
        $this->get(route('paiements.index', ['sens' => 'decaissements']))->assertSee('Holcim Madagascar')->assertDontSee('Rakoto BTP');
        $this->get(route('paiements.index', ['sens' => 'encaissements']))->assertSee('Rakoto BTP')->assertDontSee('Holcim Madagascar');
        $this->get(route('paiements.index', ['recherche' => 'rakoto']))->assertSee('REC-2026-00001')->assertDontSee('REC-2026-00002');
        $this->get(route('paiements.index', ['recherche' => 'MVOLA-77']))->assertSee('REC-2026-00002')->assertDontSee('REC-2026-00001');

        $fragment = $this->get(route('paiements.index', ['mode' => 'especes']), ['X-Fragment' => 'liste'])->getContent();
        $this->assertStringNotContainsString('<!DOCTYPE', $fragment);
        $this->assertStringContainsString('REC-2026-00001', $fragment);
        $this->assertStringNotContainsString('REC-2026-00002', $fragment);
    }

    public function test_recu_pdf_json_et_telechargement(): void
    {
        $paiement = app(PaiementService::class)->enregistrer($this->venteDe('Rakoto BTP'), 30000, ModePaiement::Especes);

        $a5 = $this->get(route('paiements.recu', $paiement))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $a5->getContent());

        $ticket = $this->get(route('paiements.recu', [$paiement, 'format' => 'ticket']))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $ticket->getContent());

        // Demandé par x-ouvrir-pdf : base64 dans du JSON (non interceptable par un gestionnaire de téléchargement)
        $json = $this->getJson(route('paiements.recu', $paiement))->assertOk()->assertJsonPath('nom', 'recu-REC-2026-00001.pdf');
        $this->assertStringStartsWith('%PDF', base64_decode($json->json('pdf')));

        $this->get(route('paiements.recu', [$paiement, 'telecharger' => 1]))
            ->assertHeader('content-disposition', 'attachment; filename=recu-REC-2026-00001.pdf');
    }

    public function test_droits_du_vendeur_et_du_magasinier(): void
    {
        $service = app(PaiementService::class);
        $encaissement = $service->enregistrer($this->venteDe('Rakoto BTP'), 30000, ModePaiement::Especes);
        $decaissement = $service->enregistrer($this->achatDe('Holcim Madagascar'), 50000, ModePaiement::Virement);

        // Vendeur (paiements.gerer sans achats.voir) : encaissements seulement, ni dettes ni reçus fournisseurs
        $this->actingAs($this->avecRole(RoleSeeder::VENDEUR));
        $this->get(route('paiements.index', ['sens' => 'decaissements']))
            ->assertOk()
            ->assertSee($encaissement->numero)
            ->assertDontSee($decaissement->numero)
            ->assertDontSee('Holcim Madagascar')
            ->assertDontSee(route('dettes.index'));
        $this->get(route('paiements.recu', $encaissement))->assertOk();
        $this->get(route('paiements.recu', $decaissement))->assertForbidden();
        $this->get(route('dettes.index'))->assertForbidden();
        $this->post(route('achats.paiements.store', $decaissement->payable), ['montant' => '1', 'mode' => 'especes', 'date_paiement' => today()->toDateString()])
            ->assertForbidden();

        $this->actingAs($this->avecRole(RoleSeeder::MAGASINIER));
        $this->get(route('paiements.index'))->assertForbidden();
        $this->get(route('paiements.recu', $encaissement))->assertForbidden();

        auth()->logout();
        $this->get(route('paiements.index'))->assertRedirect(route('connexion'));
    }
}
