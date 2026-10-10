<?php

namespace Tests\Feature\Factures;

use App\Enums\ModePaiement;
use App\Enums\TypeMouvementStock;
use App\Mail\FactureMail;
use App\Models\Client;
use App\Models\Facture;
use App\Models\JournalActivite;
use App\Models\Produit;
use App\Models\Role;
use App\Models\Utilisateur;
use App\Services\FactureService;
use App\Services\MouvementStockService;
use App\Services\VenteService;
use Database\Seeders\ClientComptoirSeeder;
use Database\Seeders\DroitSeeder;
use Database\Seeders\ParametreSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FactureHttpTest extends TestCase
{
    use RefreshDatabase;

    private Produit $produit;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed([RoleSeeder::class, DroitSeeder::class, ParametreSeeder::class, ClientComptoirSeeder::class]);
        $this->actingAs($this->avecRole(RoleSeeder::VENDEUR));
        $this->client = Client::factory()->create(['nom' => 'Rakoto BTP', 'email' => 'compta@rakoto.mg', 'telephone' => '034 12 345 67', 'plafond_credit' => 1000000]);
        $this->produit = Produit::factory()->create(['nom' => 'Ciment Holcim', 'stock_actuel' => 0, 'prix_vente' => 38000]);
        app(MouvementStockService::class)->enregistrer($this->produit, TypeMouvementStock::Achat, 100);
    }

    private function avecRole(string $role): Utilisateur
    {
        return Utilisateur::factory()->create(['role_id' => Role::where('nom', $role)->firstOrFail()->id]);
    }

    private function facture(ModePaiement $mode = ModePaiement::Especes, ?Client $client = null): Facture
    {
        return app(VenteService::class)->creer(
            $client ?? Client::where('nom', Client::COMPTOIR)->firstOrFail(),
            [['produit_id' => $this->produit->id, 'quantite' => 2]],
            0,
            $mode,
            $mode === ModePaiement::Credit ? 0 : 76000,
        )->facture;
    }

    public function test_liste_filtres_recherche_et_fragment(): void
    {
        $payee = $this->facture();
        $credit = $this->facture(ModePaiement::Credit, $this->client);
        $annulee = $this->facture();
        app(VenteService::class)->annuler($annulee->vente, 'Erreur de caisse');

        $this->get(route('factures.index'))
            ->assertOk()
            ->assertSee($payee->numero)
            ->assertSee($credit->numero)
            ->assertSee('Montant facturé');

        $this->get(route('factures.index', ['statut' => 'annulees']))->assertSee($annulee->numero)->assertDontSee($payee->numero);
        $this->get(route('factures.index', ['paiement' => 'credit']))->assertSee($credit->numero)->assertDontSee($payee->numero);
        $this->get(route('factures.index', ['recherche' => 'Rakoto']))->assertSee($credit->numero)->assertDontSee($payee->numero);
        $this->get(route('factures.index', ['recherche' => $payee->vente->numero]))->assertSee($payee->numero)->assertDontSee($credit->numero);

        $fragment = $this->get(route('factures.index', ['statut' => 'emises']), ['X-Fragment' => 'liste'])->getContent();
        $this->assertStringNotContainsString('<!DOCTYPE', $fragment);
        $this->assertStringNotContainsString($annulee->numero, $fragment);
    }

    public function test_pdf_genere_a4_ticket_json_et_telechargement(): void
    {
        $facture = $this->facture();

        $a4 = $this->get(route('factures.pdf', $facture))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $a4->getContent());

        $ticket = $this->get(route('factures.pdf', [$facture, 'format' => 'ticket']))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $ticket->getContent());

        // Demandé par l'aperçu ou x-ouvrir-pdf : base64 dans du JSON (non interceptable par un gestionnaire de téléchargement)
        $json = $this->getJson(route('factures.pdf', $facture))->assertOk()->assertJsonPath('nom', "facture-{$facture->numero}.pdf");
        $this->assertStringStartsWith('%PDF', base64_decode($json->json('pdf')));

        $this->get(route('factures.pdf', [$facture, 'telecharger' => 1]))
            ->assertOk()
            ->assertHeader('content-disposition', "attachment; filename=facture-{$facture->numero}.pdf");
    }

    public function test_contenu_du_document_et_filigrane_si_annulee(): void
    {
        $facture = $this->facture(ModePaiement::Credit, $this->client);
        $html = view('factures.a4', app(FactureService::class)->donnees($facture))->render();

        $this->assertStringContainsString($facture->numero, $html);
        $this->assertStringContainsString('Rakoto BTP', $html);
        $this->assertStringContainsString('Total à payer', $html);
        $this->assertStringContainsString('data:image/png;base64,', $html, 'Code-barres du numéro.');
        $this->assertStringNotContainsString('ANNULÉE', $html);
        $this->assertStringNotContainsString('dont TVA', $html);

        app(VenteService::class)->annuler($facture->vente, 'Erreur');
        $this->assertStringContainsString('ANNULÉE', view('factures.a4', app(FactureService::class)->donnees($facture->fresh()))->render());
        $this->assertStringContainsString('FACTURE ANNULÉE', view('factures.ticket', app(FactureService::class)->donnees($facture->fresh()))->render());
        $this->get(route('factures.pdf', $facture))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_apercu_json(): void
    {
        $facture = $this->facture(ModePaiement::Credit, $this->client);

        $this->getJson(route('factures.show', $facture))
            ->assertOk()
            ->assertJsonPath('numero', $facture->numero)
            ->assertJsonPath('client', 'Rakoto BTP')
            ->assertJsonPath('email', 'compta@rakoto.mg')
            ->assertJsonPath('urls.ticket', route('factures.pdf', [$facture, 'format' => 'ticket']));
    }

    public function test_envoi_par_email_avec_piece_jointe_et_journal(): void
    {
        Mail::fake();
        $facture = $this->facture(ModePaiement::Credit, $this->client);

        $this->postJson(route('factures.envoyer', $facture), ['email' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email' => 'Indiquez l\'adresse email du destinataire.']);

        $this->postJson(route('factures.envoyer', $facture), ['email' => 'compta@rakoto.mg', 'message' => 'Merci pour votre achat.'])
            ->assertOk()
            ->assertJsonPath('message', "Facture {$facture->numero} envoyée à compta@rakoto.mg.");

        Mail::assertSent(FactureMail::class, function (FactureMail $mail) use ($facture) {
            $pieces = $mail->attachments();

            return $mail->hasTo('compta@rakoto.mg')
                && $mail->messagePersonnel === 'Merci pour votre achat.'
                && count($pieces) === 1
                && $pieces[0]->as === "facture-{$facture->numero}.pdf";
        });
        $this->assertDatabaseHas('journal_activites', ['action' => 'facture.envoyee', 'modele' => 'facture', 'modele_id' => $facture->id]);

        // Le message est rendu en français avec le numéro et le total
        $this->assertStringContainsString($facture->numero, (new FactureMail($facture, null, app(FactureService::class)))->render());
    }

    public function test_lien_de_partage_signe_et_expirant(): void
    {
        $facture = $this->facture(ModePaiement::Credit, $this->client);
        $autre = $this->facture();

        $reponse = $this->postJson(route('factures.partager', $facture))->assertOk();
        $url = $reponse->json('url');
        $this->assertStringStartsWith('https://wa.me/261341234567?text=', $reponse->json('whatsapp'));
        $this->assertStringContainsString(rawurlencode($url), $reponse->json('whatsapp'));
        $this->assertSame(1, JournalActivite::where('action', 'facture.partagee')->count());

        // Sans compte : le lien signé donne le PDF de cette facture uniquement
        auth()->logout();
        $pdf = $this->get($url)->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->get(str_replace("/f/{$facture->id}?", "/f/{$autre->id}?", $url))->assertForbidden();
        $this->get(route('factures.publique', $facture))->assertForbidden();

        $this->travel(8)->days();
        $this->get($url)->assertForbidden();
    }

    public function test_droits(): void
    {
        $facture = $this->facture();

        $this->actingAs($this->avecRole(RoleSeeder::MAGASINIER));
        $this->get(route('factures.index'))->assertForbidden();
        $this->get(route('factures.pdf', $facture))->assertForbidden();
        $this->postJson(route('factures.partager', $facture))->assertForbidden();
        $this->postJson(route('factures.envoyer', $facture), ['email' => 'a@b.mg'])->assertForbidden();

        auth()->logout();
        $this->get(route('factures.index'))->assertRedirect(route('connexion'));
    }
}
