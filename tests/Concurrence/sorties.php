<?php

/*
 * Processus de travail du test de concurrence du stock (lancé par ConcurrenceStockTest).
 * Démarre l'application sur la base MySQL de test, attend l'instant de départ commun,
 * puis tente N sorties de 1 unité sur le même produit. Affiche un bilan JSON.
 *
 * Usage : php sorties.php <produit_id> <utilisateur_id> <tentatives> <depart_microtime>
 */

use App\Enums\TypeMouvementStock;
use App\Exceptions\StockInsuffisantException;
use App\Models\Produit;
use App\Models\Utilisateur;
use App\Services\MouvementStockService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

[, $produitId, $utilisateurId, $tentatives, $depart] = $argv;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

config(['database.default' => 'mysql_test']);
DB::purge('mysql_test');

Auth::setUser(Utilisateur::findOrFail((int) $utilisateurId));
$service = $app->make(MouvementStockService::class);

// Départ synchronisé des deux processus
while (microtime(true) < (float) $depart) {
    usleep(1000);
}

$bilan = ['reussies' => 0, 'refusees' => 0, 'erreurs' => []];

for ($i = 0; $i < (int) $tentatives; $i++) {
    try {
        // Instance volontairement rechargée à chaque tour : le service doit relire la ligne verrouillée
        $service->enregistrer(Produit::findOrFail((int) $produitId), TypeMouvementStock::Vente, 1, null, 'Test de concurrence');
        $bilan['reussies']++;
    } catch (StockInsuffisantException) {
        $bilan['refusees']++;
    } catch (Throwable $erreur) {
        $bilan['erreurs'][] = $erreur->getMessage();
    }
}

echo json_encode($bilan);
