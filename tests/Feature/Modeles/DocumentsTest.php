<?php

namespace Tests\Feature\Modeles;

use App\Enums\ModePaiement;
use App\Enums\StatutVente;
use App\Enums\TypeMouvementStock;
use App\Models\Achat;
use App\Models\Client;
use App\Models\Facture;
use App\Models\Fournisseur;
use App\Models\Inventaire;
use App\Models\LigneAchat;
use App\Models\LigneInventaire;
use App\Models\LigneRetour;
use App\Models\LigneVente;
use App\Models\MouvementStock;
use App\Models\Paiement;
use App\Models\Retour;
use App\Models\Vente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_vente_relations_et_casts(): void
    {
        $vente = Vente::factory()->create();
        LigneVente::factory()->count(2)->for($vente)->create();
        Facture::factory()->for($vente)->create();
        Paiement::factory()->create(['payable_type' => 'vente', 'payable_id' => $vente->id]);

        $vente->refresh();

        $this->assertCount(2, $vente->lignes);
        $this->assertSame($vente->total, $vente->facture->total);
        $this->assertCount(1, $vente->paiements);
        $this->assertTrue($vente->paiements->first()->payable->is($vente));
        $this->assertInstanceOf(Client::class, $vente->client);
        $this->assertSame(StatutVente::Validee, $vente->statut);
        $this->assertSame(ModePaiement::Especes, $vente->mode_paiement);
        $this->assertInstanceOf(Carbon::class, $vente->date_vente);
    }

    public function test_achat_relations(): void
    {
        $achat = Achat::factory()->create();
        LigneAchat::factory()->count(3)->for($achat)->create();
        Paiement::factory()->pourAchat()->create(['payable_id' => $achat->id]);

        $this->assertCount(3, $achat->lignes);
        $this->assertCount(1, $achat->paiements);
        $this->assertTrue($achat->fournisseur->achats->contains($achat));
    }

    public function test_les_types_polymorphes_sont_enregistres_en_francais(): void
    {
        $vente = Vente::factory()->create();
        $paiement = $vente->paiements()->create([
            'montant' => 10000,
            'mode' => ModePaiement::Especes,
            'date_paiement' => now(),
            'utilisateur_id' => $vente->utilisateur_id,
        ]);
        $mouvement = MouvementStock::factory()->create([
            'type' => TypeMouvementStock::Vente,
            'sens' => TypeMouvementStock::Vente->sens(),
            'reference_type' => 'vente',
            'reference_id' => $vente->id,
        ]);

        $this->assertSame('vente', DB::table('paiements')->where('id', $paiement->id)->value('payable_type'));
        $this->assertTrue($mouvement->reference->is($vente));
        $this->assertTrue($vente->mouvementsStock->contains($mouvement));
    }

    public function test_est_soldee(): void
    {
        $this->assertTrue(Vente::factory()->make()->est_soldee);
        $this->assertFalse(Vente::factory()->aCredit(20000)->make()->est_soldee);
        $this->assertTrue(Achat::factory()->make()->est_soldee);
        $this->assertFalse(Achat::factory()->nonSolde(80000)->make()->est_soldee);
    }

    public function test_creance_totale_du_client_ignore_les_ventes_annulees(): void
    {
        $client = Client::factory()->create();
        Vente::factory()->for($client)->aCredit(20000)->create();
        Vente::factory()->for($client)->aCredit(15000)->create();
        Vente::factory()->for($client)->aCredit(99000)->annulee()->create();
        Vente::factory()->for($client)->create();

        $this->assertSame(35000.0, $client->creance_totale);
        $this->assertSame(0.0, Client::factory()->create()->creance_totale);
    }

    public function test_dette_totale_du_fournisseur_ignore_les_achats_annules(): void
    {
        $fournisseur = Fournisseur::factory()->create();
        Achat::factory()->for($fournisseur)->nonSolde(100000)->create();
        Achat::factory()->for($fournisseur)->nonSolde(50000)->annule()->create();
        Achat::factory()->for($fournisseur)->create();

        $this->assertSame(100000.0, $fournisseur->dette_totale);
    }

    public function test_client_comptoir(): void
    {
        $this->assertTrue(Client::factory()->comptoir()->make()->estComptoir());
        $this->assertFalse(Client::factory()->make()->estComptoir());
    }

    public function test_retour_et_inventaire_relations(): void
    {
        $retourClient = Retour::factory()->create();
        LigneRetour::factory()->for($retourClient)->create();
        $retourFournisseur = Retour::factory()->fournisseur()->create();

        $this->assertCount(1, $retourClient->lignes);
        $this->assertTrue($retourClient->vente->retours->contains($retourClient));
        $this->assertNull($retourFournisseur->vente);
        $this->assertTrue($retourFournisseur->achat->retours->contains($retourFournisseur));

        $inventaire = Inventaire::factory()->create();
        $ligne = LigneInventaire::factory()->for($inventaire)->create(['stock_theorique' => 20]);
        $comptee = LigneInventaire::factory()->for($inventaire)->comptee(20, 18)->create();

        $this->assertCount(2, $inventaire->lignes);
        $this->assertNull($ligne->ecart);
        $this->assertEquals(-2, $comptee->ecart);
    }

    public function test_mouvement_et_journal_n_ont_pas_de_updated_at(): void
    {
        $mouvement = MouvementStock::factory()->create();

        $this->assertNotNull($mouvement->created_at);
        $this->assertNull($mouvement->getUpdatedAtColumn());
    }
}
