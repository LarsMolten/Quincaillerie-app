<?php

namespace Tests\Feature\Services;

use App\Enums\ModePaiement;
use App\Enums\TypeMouvementStock;
use App\Models\Achat;
use App\Models\Client;
use App\Models\Facture;
use App\Models\Fournisseur;
use App\Models\LigneRetour;
use App\Models\Paiement;
use App\Models\Produit;
use App\Models\Utilisateur;
use App\Models\Vente;
use App\Services\MouvementStockService;
use App\Services\VenteService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Numérotation sans trou sous concurrence réelle : deux processus créent des documents en même temps.
 * Exige MySQL (base TEST_MYSQL_DATABASE finissant par « _test ») ; ignoré sinon.
 */
#[Group('concurrence')]
class ConcurrenceNumerotationTest extends TestCase
{
    private const DOCUMENTS_PAR_PROCESSUS = 10;

    protected function setUp(): void
    {
        parent::setUp();

        $base = (string) env('TEST_MYSQL_DATABASE');

        if ($base === '') {
            $this->markTestSkipped('Test de concurrence ignoré : définissez TEST_MYSQL_DATABASE (base MySQL dédiée finissant par « _test »).');
        }

        $this->assertStringEndsWith('_test', $base, 'La base de concurrence doit finir par « _test ».');

        config(['database.default' => 'mysql_test']);
        DB::purge('mysql_test');
        Artisan::call('migrate:fresh', ['--database' => 'mysql_test', '--force' => true]);
    }

    public function test_numeros_d_achat_uniques_et_consecutifs_sans_trou(): void
    {
        $utilisateur = Utilisateur::factory()->create();
        $fournisseur = Fournisseur::factory()->create();
        $produit = Produit::factory()->create(['stock_actuel' => 0]);

        $bilans = $this->lancer('achats.php', [$fournisseur->id, $produit->id, $utilisateur->id]);

        $total = 2 * self::DOCUMENTS_PAR_PROCESSUS;
        $this->assertSame($this->attendus('ACH', $total), $this->numeros($bilans, 'numeros'), 'Numéros uniques, consécutifs et sans trou.');
        $this->assertSame($total, Achat::count());
        $this->assertEquals($total, $produit->fresh()->stock_actuel);
    }

    public function test_numeros_de_vente_et_de_facture_uniques_et_consecutifs_sans_trou(): void
    {
        $utilisateur = Utilisateur::factory()->create();
        $this->actingAs($utilisateur);
        $client = Client::factory()->create(['nom' => Client::COMPTOIR, 'plafond_credit' => null]);
        $produit = Produit::factory()->create(['stock_actuel' => 0, 'prix_vente' => 1000]);
        app(MouvementStockService::class)->enregistrer($produit, TypeMouvementStock::Achat, 100);

        $bilans = $this->lancer('ventes.php', [$client->id, $produit->id, $utilisateur->id]);

        $total = 2 * self::DOCUMENTS_PAR_PROCESSUS;
        $this->assertSame($this->attendus('VTE', $total), $this->numeros($bilans, 'numeros'), 'Numéros de vente sans trou.');
        $this->assertSame($this->attendus('FAC', $total), $this->numeros($bilans, 'factures'), 'Numéros de facture sans trou.');
        $this->assertSame($total, Vente::count());
        $this->assertSame($total, Facture::count());
        $this->assertSame($this->attendus('REC', $total), Paiement::orderBy('numero')->pluck('numero')->all(), 'Numéros de reçu sans trou.');
        $this->assertEquals(100 - $total, $produit->fresh()->stock_actuel);
    }

    public function test_paiements_simultanes_sur_une_meme_vente_sans_depassement_ni_doublon(): void
    {
        $utilisateur = Utilisateur::factory()->create();
        $reste = 15;
        $vente = Vente::factory()->create(['total' => $reste * 1000, 'montant_paye' => 0, 'reste_a_payer' => $reste * 1000]);

        // 2 × 10 tentatives de 1 000 Ar pour un reste de 15 000 Ar : 15 acceptées, 5 refusées
        $bilans = $this->lancer('paiements.php', [$vente->id, $utilisateur->id]);

        $this->assertSame($this->attendus('REC', $reste), $this->numeros($bilans, 'numeros'), 'Numéros de reçu uniques et sans trou.');
        $this->assertCount(2 * self::DOCUMENTS_PAR_PROCESSUS - $reste, array_merge(...array_column($bilans, 'refus')), 'Les paiements en trop sont refusés.');
        $this->assertEquals(0, $vente->fresh()->reste_a_payer, 'Le reste n\'est jamais dépassé.');
        $this->assertEquals($reste * 1000, $vente->fresh()->montant_paye);
        $this->assertEquals($reste * 1000, Paiement::sum('montant'));
    }

    public function test_retours_simultanes_sur_une_meme_vente_sans_depassement_ni_doublon(): void
    {
        $utilisateur = Utilisateur::factory()->create();
        $this->actingAs($utilisateur);
        $client = Client::factory()->create(['nom' => Client::COMPTOIR, 'plafond_credit' => null]);
        $produit = Produit::factory()->create(['stock_actuel' => 0, 'prix_vente' => 1000]);
        app(MouvementStockService::class)->enregistrer($produit, TypeMouvementStock::Achat, 100);
        $vendus = 15;
        $vente = app(VenteService::class)->creer($client, [['produit_id' => $produit->id, 'quantite' => $vendus]], 0, ModePaiement::Especes, 15000);

        // 2 × 10 tentatives d'une unité pour 15 unités vendues : 15 acceptées, 5 refusées
        $bilans = $this->lancer('retours.php', [$vente->id, $produit->id, $utilisateur->id]);

        $this->assertSame($this->attendus('RET', $vendus), $this->numeros($bilans, 'numeros'), 'Numéros de retour uniques et sans trou.');
        $this->assertCount(2 * self::DOCUMENTS_PAR_PROCESSUS - $vendus, array_merge(...array_column($bilans, 'refus')), 'Les retours en trop sont refusés.');
        $this->assertEquals($vendus, LigneRetour::sum('quantite'), 'Jamais plus que la quantité vendue.');
        $this->assertEquals(100, $produit->fresh()->stock_actuel, 'Tout le vendu est revenu en stock, une seule fois.');
    }

    /**
     * Lance deux processus de travail qui démarrent au même instant et renvoie leurs bilans JSON.
     *
     * @return list<array{numeros: list<string>, erreurs: list<string>}>
     */
    private function lancer(string $script, array $arguments): array
    {
        $depart = microtime(true) + 2.5;
        $processus = [];

        foreach ([1, 2] as $numero) {
            $commande = [PHP_BINARY, base_path("tests/Concurrence/{$script}"), ...array_map('strval', $arguments),
                (string) self::DOCUMENTS_PAR_PROCESSUS, (string) $depart];
            $tubes = [];
            $processus[$numero] = [proc_open($commande, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tubes, base_path()), $tubes];
        }

        $bilans = [];
        foreach ($processus as $numero => [$ressource, $tubes]) {
            $sortie = stream_get_contents($tubes[1]);
            $erreurs = stream_get_contents($tubes[2]);
            fclose($tubes[1]);
            fclose($tubes[2]);
            $this->assertSame(0, proc_close($ressource), "Processus {$numero} en erreur : {$erreurs}");
            $bilan = json_decode($sortie, true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame([], $bilan['erreurs'], "Erreurs dans le processus {$numero}.");
            $this->assertNotEmpty($bilan['numeros'], 'Chaque processus doit avoir créé des documents.');
            $bilans[] = $bilan;
        }

        return $bilans;
    }

    private function numeros(array $bilans, string $cle): array
    {
        $numeros = array_merge(...array_column($bilans, $cle));
        sort($numeros);

        return $numeros;
    }

    private function attendus(string $prefixe, int $total): array
    {
        return array_map(fn (int $i) => sprintf('%s-%d-%05d', $prefixe, now()->year, $i), range(1, $total));
    }
}
