<?php

/*
 * Processus de travail du test de concurrence des retours (ConcurrenceNumerotationTest).
 * Démarre l'application sur la base MySQL de test, attend l'instant de départ commun,
 * puis tente N retours d'une unité du même produit sur la même vente. Affiche un bilan JSON :
 * numéros RET obtenus, refus attendus (quantité retournable dépassée) et erreurs inattendues.
 *
 * Usage : php retours.php <vente_id> <produit_id> <utilisateur_id> <nombre> <depart_microtime>
 */

use App\Enums\ModePaiement;
use App\Exceptions\OperationRefuseeException;
use App\Models\Utilisateur;
use App\Models\Vente;
use App\Services\RetourService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

[, $venteId, $produitId, $utilisateurId, $nombre, $depart] = $argv;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

config(['database.default' => 'mysql_test']);
DB::purge('mysql_test');

Auth::setUser(Utilisateur::findOrFail((int) $utilisateurId));
$service = $app->make(RetourService::class);
$vente = Vente::findOrFail((int) $venteId);

while (microtime(true) < (float) $depart) {
    usleep(1000);
}

$bilan = ['numeros' => [], 'refus' => [], 'erreurs' => []];

for ($i = 0; $i < (int) $nombre; $i++) {
    try {
        $bilan['numeros'][] = $service->creerClient($vente, [(int) $produitId => 1], 'Produit défectueux', ModePaiement::Especes)->numero;
    } catch (OperationRefuseeException $refus) {
        $bilan['refus'][] = $refus->getMessage();
    } catch (Throwable $erreur) {
        $bilan['erreurs'][] = $erreur->getMessage();
    }
}

echo json_encode($bilan);
