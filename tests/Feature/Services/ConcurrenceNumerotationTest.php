<?php

namespace Tests\Feature\Services;

use App\Models\Achat;
use App\Models\Fournisseur;
use App\Models\Produit;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Numérotation sans trou sous concurrence réelle : deux processus créent des achats en même temps.
 * Exige MySQL (base TEST_MYSQL_DATABASE finissant par « _test ») ; ignoré sinon.
 */
#[Group('concurrence')]
class ConcurrenceNumerotationTest extends TestCase
{
    private const ACHATS_PAR_PROCESSUS = 10;

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

    public function test_numeros_uniques_et_consecutifs_sans_trou(): void
    {
        $utilisateur = Utilisateur::factory()->create();
        $fournisseur = Fournisseur::factory()->create();
        $produit = Produit::factory()->create(['stock_actuel' => 0]);

        $depart = microtime(true) + 2.5;
        $processus = [];
        foreach ([1, 2] as $numero) {
            $commande = [PHP_BINARY, base_path('tests/Concurrence/achats.php'), (string) $fournisseur->id, (string) $produit->id,
                (string) $utilisateur->id, (string) self::ACHATS_PAR_PROCESSUS, (string) $depart];
            $tubes = [];
            $processus[$numero] = [proc_open($commande, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tubes, base_path()), $tubes];
        }

        $numeros = [];
        foreach ($processus as $numero => [$ressource, $tubes]) {
            $sortie = stream_get_contents($tubes[1]);
            $erreurs = stream_get_contents($tubes[2]);
            fclose($tubes[1]);
            fclose($tubes[2]);
            $this->assertSame(0, proc_close($ressource), "Processus {$numero} en erreur : {$erreurs}");
            $bilan = json_decode($sortie, true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame([], $bilan['erreurs'], "Erreurs dans le processus {$numero}.");
            $this->assertNotEmpty($bilan['numeros'], 'Chaque processus doit avoir créé des achats.');
            $numeros = [...$numeros, ...$bilan['numeros']];
        }

        $total = 2 * self::ACHATS_PAR_PROCESSUS;
        sort($numeros);
        $attendus = array_map(fn (int $i) => sprintf('ACH-%d-%05d', now()->year, $i), range(1, $total));

        $this->assertSame($attendus, $numeros, 'Numéros uniques, consécutifs et sans trou.');
        $this->assertSame($total, Achat::count());
        $this->assertEquals($total, $produit->fresh()->stock_actuel);
    }
}
