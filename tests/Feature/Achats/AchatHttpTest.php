<?php

namespace Tests\Feature\Achats;

use App\Enums\ModePaiement;
use App\Enums\StatutAchat;
use App\Models\Achat;
use App\Models\Droit;
use App\Models\Fournisseur;
use App\Models\Produit;
use App\Models\Role;
use App\Models\Utilisateur;
use App\Services\AchatService;
use Database\Seeders\DroitSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AchatHttpTest extends TestCase
{
    use RefreshDatabase;

    private Fournisseur $fournisseur;

    private Produit $ciment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed([RoleSeeder::class, DroitSeeder::class]);
        $this->actingAs($this->avecRole(RoleSeeder::RESPONSABLE));
        $this->fournisseur = Fournisseur::factory()->create(['nom' => 'Ravinala Matériaux']);
        $this->ciment = Produit::factory()->create(['nom' => 'Ciment Holcim', 'code_barres' => '2000000000015', 'stock_actuel' => 0, 'prix_achat' => 33000]);
    }

    private function avecRole(string $role): Utilisateur
    {
        return Utilisateur::factory()->create(['role_id' => Role::where('nom', $role)->firstOrFail()->id]);
    }

    private function donnees(array $surcharges = []): array
    {
        return [
            'fournisseur_id' => $this->fournisseur->id,
            'date_achat' => today()->toDateString(),
            'lignes' => [['produit_id' => $this->ciment->id, 'quantite' => '40', 'prix_achat' => '33 000']],
            'montant_paye' => '500000',
            'mode_paiement' => 'especes',
            'maj_prix' => '1',
            ...$surcharges,
        ];
    }

    private function achat(float $paye = 0): Achat
    {
        return app(AchatService::class)->creer($this->fournisseur, [['produit_id' => $this->ciment->id, 'quantite' => 10, 'prix_achat' => 33000]], $paye, $paye > 0 ? ModePaiement::Especes : null);
    }

    public function test_ecran_de_saisie(): void
    {
        $this->get(route('achats.create'))
            ->assertOk()
            ->assertSee('Nouvel achat')
            ->assertSee('Récapitulatif')
            ->assertSee('Ravinala Matériaux')
            ->assertSee("Enregistrer l'achat", false)
            ->assertSee('À crédit');
    }

    public function test_recherche_produits_actifs_et_code_barres(): void
    {
        Produit::factory()->inactif()->create(['nom' => 'Ciment ancien']);

        $this->getJson(route('achats.produits', ['q' => 'ciment']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.nom', 'Ciment Holcim')
            ->assertJsonPath('0.prix_actuel', 33000);

        $this->getJson(route('achats.produits', ['q' => '2000000000015']))->assertJsonPath('0.produit_id', $this->ciment->id);
        $this->getJson(route('achats.produits', ['q' => '']))->assertExactJson([]);
    }

    public function test_enregistrement_et_toast_avec_lien_vers_le_bon(): void
    {
        $reponse = $this->post(route('achats.store'), $this->donnees());

        $achat = Achat::firstOrFail();
        $reponse->assertRedirect(route('achats.show', $achat))
            ->assertSessionHas('succes', "Achat {$achat->numero} enregistré : stock mis à jour.")
            ->assertSessionHas('toast_lien', fn ($lien) => $lien['url'] === route('achats.bon', $achat));

        $this->assertEquals(40, $this->ciment->fresh()->stock_actuel);
        $this->assertEquals(1320000 - 500000, $achat->reste_a_payer);

        // Le toast porte le lien sur la page suivante
        $this->get(route('achats.show', $achat))->assertSee('Voir le bon d', false)->assertSee(route('achats.bon', $achat), false);
    }

    public function test_validation_en_francais_avec_ligne_indiquee(): void
    {
        $this->post(route('achats.store'), $this->donnees(['lignes' => []]))
            ->assertSessionHasErrors(['lignes' => 'Ajoutez au moins un produit.']);

        $this->post(route('achats.store'), $this->donnees([
            'lignes' => [
                ['produit_id' => $this->ciment->id, 'quantite' => '2', 'prix_achat' => '1000'],
                ['produit_id' => $this->ciment->id, 'quantite' => '0', 'prix_achat' => '1000'],
            ],
        ]))->assertSessionHasErrors(['lignes.1.quantite' => 'La ligne 2 : quantité doit être supérieure à 0.']);

        $this->post(route('achats.store'), $this->donnees(['montant_paye' => '9999999999']))
            ->assertSessionHasErrors('montant_paye');

        $this->post(route('achats.store'), $this->donnees(['mode_paiement' => '']))
            ->assertSessionHasErrors(['mode_paiement' => 'Choisissez le mode de paiement du montant versé.']);

        $this->post(route('achats.store'), $this->donnees(['date_achat' => today()->addDay()->toDateString()]))
            ->assertSessionHasErrors(['date_achat' => 'La date de l\'achat ne peut pas être dans le futur.']);

        $this->assertSame(0, Achat::count());
    }

    public function test_les_lignes_sont_restaurees_apres_une_erreur(): void
    {
        $this->from(route('achats.create'))->post(route('achats.store'), $this->donnees(['fournisseur_id' => '']));

        $this->get(route('achats.create'))->assertSee('Ciment Holcim');
    }

    public function test_liste_filtres_et_badges(): void
    {
        $credit = $this->achat();
        $paye = $this->achat(330000);
        $ancien = $this->achat();
        $ancien->update(['date_achat' => today()->subDays(40)]);
        $autre = Fournisseur::factory()->create(['nom' => 'Autre fournisseur']);

        $this->get(route('achats.index'))
            ->assertOk()
            ->assertSee($credit->numero)
            ->assertSee('À crédit')
            ->assertSee('Payé');

        $this->get(route('achats.index', ['paiement' => 'credit']))->assertSee($credit->numero)->assertDontSee($paye->numero);
        $this->get(route('achats.index', ['paiement' => 'paye']))->assertSee($paye->numero)->assertDontSee($credit->numero);
        $this->get(route('achats.index', ['periode' => '30j']))->assertSee($credit->numero)->assertDontSee($ancien->numero);
        $this->get(route('achats.index', ['fournisseur' => $autre->id]))->assertSee('Aucun achat trouvé');

        $fragment = $this->get(route('achats.index', ['paiement' => 'credit']), ['X-Fragment' => 'liste'])->getContent();
        $this->assertStringNotContainsString('<!DOCTYPE', $fragment);
    }

    public function test_fiche_et_bon_pdf(): void
    {
        $achat = $this->achat(100000);

        $this->get(route('achats.show', $achat))
            ->assertOk()
            ->assertSee($achat->numero)
            ->assertSee('Ciment Holcim')
            ->assertSee('Ajouter un paiement')
            ->assertSee("Annuler l'achat", false);

        $pdf = $this->get(route('achats.bon', $achat))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_paiement_par_la_route(): void
    {
        $achat = $this->achat();

        $this->post(route('achats.paiements.store', $achat), ['montant' => '330 000', 'mode' => 'virement', 'date_paiement' => today()->toDateString()])
            ->assertSessionHas('succes', "Paiement enregistré : l'achat {$achat->numero} est soldé.");

        $this->post(route('achats.paiements.store', $achat), ['montant' => '1000', 'mode' => 'especes', 'date_paiement' => today()->toDateString()])
            ->assertSessionHas('erreur');
    }

    public function test_annulation_par_la_route_avec_motif_obligatoire(): void
    {
        $achat = $this->achat();

        $this->post(route('achats.annuler', $achat), ['motif' => ''])->assertSessionHasErrors('motif');
        $this->assertSame(StatutAchat::Valide, $achat->fresh()->statut);

        $this->post(route('achats.annuler', $achat), ['motif' => 'Marchandise refusée'])
            ->assertSessionHas('succes', "Achat {$achat->numero} annulé : le stock a été corrigé.");
        $this->assertSame(StatutAchat::Annule, $achat->fresh()->statut);

        $this->get(route('achats.show', $achat))->assertSee('Achat annulé')->assertSee('Marchandise refusée')->assertDontSee('Ajouter un paiement');
    }

    public function test_droits(): void
    {
        $achat = $this->achat();

        foreach ([RoleSeeder::VENDEUR, RoleSeeder::MAGASINIER] as $role) {
            $this->actingAs($this->avecRole($role));
            $this->get(route('achats.index'))->assertForbidden();
            $this->get(route('achats.create'))->assertForbidden();
            $this->post(route('achats.store'), $this->donnees())->assertForbidden();
            $this->post(route('achats.annuler', $achat), ['motif' => 'Tentative'])->assertForbidden();
        }

        // Consultation seule : ni création, ni paiement, ni annulation
        $lecteur = Utilisateur::factory()->create();
        $lecteur->role->droits()->attach(Droit::where('code', 'achats.voir')->value('id'));
        $this->actingAs($lecteur);
        $this->get(route('achats.index'))->assertOk()->assertDontSee('Nouvel achat');
        $this->get(route('achats.show', $achat))->assertOk()->assertDontSee("Annuler l'achat", false)->assertDontSee('Enregistrer le paiement');
        $this->get(route('achats.create'))->assertForbidden();
        $this->post(route('achats.paiements.store', $achat), ['montant' => '1', 'mode' => 'especes', 'date_paiement' => today()->toDateString()])->assertForbidden();
        $this->post(route('achats.annuler', $achat), ['motif' => 'Tentative'])->assertForbidden();
    }
}
