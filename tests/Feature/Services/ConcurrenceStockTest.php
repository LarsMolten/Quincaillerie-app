<?php

namespace Tests\Feature\Services;

use App\Enums\TypeMouvementStock;
use App\Models\MouvementStock;
use App\Models\Produit;
use App\Models\Utilisateur;
use App\Services\MouvementStockService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Vraie concurrence : deux processus PHP vident le même stock en même temps.
 * Exige MySQL (SQLite en mémoire ignore les verrous) : base TEST_MYSQL_DATABASE, nom en « _test ».
 * Ignoré si cette base n'est pas configurée.
 */
#[Group('concurrence')]
class ConcurrenceStockTest extends TestCase
{
    private const STOCK_INITIAL = 40;

    private const TENTATIVES_PAR_PROCESSUS = 30;

    protected function setUp(): void
    {
        parent::setUp();

        $base = (string) env('TEST_MYSQL_DATABASE');

        if ($base === '') {
            $this->markTestSkipped('Test de concurrence ignoré : définissez TEST_MYSQL_DATABASE (base MySQL dédiée finissant par « _test »).');
        }

        // Garde-fou : jamais la base de démonstration ou de production
        $this->assertStringEndsWith('_test', $base, 'La base de concurrence doit finir par « _test ».');

        config(['database.default' => 'mysql_test']);
        DB::purge('mysql_test');
        Artisan::call('migrate:fresh', ['--database' => 'mysql_test', '--force' => true]);
    }

    public function test_deux_processus_ne_peuvent_pas_vendre_le_meme_stock(): void
    {
        $utilisateur = Utilisateur::factory()->create();
        $produit = Produit::factory()->create(['stock_actuel' => 0, 'stock_minimum' => 0]);
        $this->actingAs($utilisateur);
        app(MouvementStockService::class)->enregistrer($produit, TypeMouvementStock::AjustementPositif, self::STOCK_INITIAL, null, 'Stock initial');

        // Deux processus démarrent au même instant
        $depart = microtime(true) + 2.5;
        $processus = [];
        foreach ([1, 2] as $numero) {
            $commande = [PHP_BINARY, base_path('tests/Concurrence/sorties.php'), (string) $produit->id, (string) $utilisateur->id, (string) self::TENTATIVES_PAR_PROCESSUS, (string) $depart];
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
            $bilans[$numero] = json_decode($sortie, true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame([], $bilans[$numero]['erreurs'], "Erreurs inattendues dans le processus {$numero}.");
        }

        $reussies = array_sum(array_column($bilans, 'reussies'));
        $refusees = array_sum(array_column($bilans, 'refusees'));

        // Exactement le stock disponible a été vendu, le reste refusé
        $this->assertSame(self::STOCK_INITIAL, $reussies);
        $this->assertSame(2 * self::TENTATIVES_PAR_PROCESSUS - self::STOCK_INITIAL, $refusees);
        $this->assertGreaterThan(0, min(array_column($bilans, 'reussies')), 'Les deux processus doivent avoir vendu (vraie concurrence).');

        $this->assertEquals(0, $produit->fresh()->stock_actuel);
        $this->assertSame(0, MouvementStock::where('stock_apres', '<', 0)->count());

        // Lectures sérialisées : chaque sortie part d'un stock différent (40, 39, …, 1)
        $stocksAvant = MouvementStock::where('type', 'vente')->pluck('stock_avant')->map(fn ($v) => (float) $v);
        $this->assertCount(self::STOCK_INITIAL, $stocksAvant->unique());

        $this->assertTrue(app(MouvementStockService::class)->verifierCoherence()->isEmpty());
    }
}
