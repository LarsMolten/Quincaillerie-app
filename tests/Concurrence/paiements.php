<?php

/*
 * Processus de travail du test de concurrence des paiements (ConcurrenceNumerotationTest).
 * Démarre l'application sur la base MySQL de test, attend l'instant de départ commun,
 * puis tente N paiements de 1 000 Ar sur la même vente. Affiche un bilan JSON :
 * numéros de reçu obtenus, refus attendus (reste dépassé) et erreurs inattendues.
 *
 * Usage : php paiements.php <vente_id> <utilisateur_id> <nombre> <depart_microtime>
 */

use App\Enums\ModePaiement;
use App\Exceptions\OperationRefuseeException;
use App\Models\Utilisateur;
use App\Models\Vente;
use App\Services\PaiementService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

[, $venteId, $utilisateurId, $nombre, $depart] = $argv;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

config(['database.default' => 'mysql_test']);
DB::purge('mysql_test');

Auth::setUser(Utilisateur::findOrFail((int) $utilisateurId));
$service = $app->make(PaiementService::class);
$vente = Vente::findOrFail((int) $venteId);

while (microtime(true) < (float) $depart) {
    usleep(1000);
}

$bilan = ['numeros' => [], 'refus' => [], 'erreurs' => []];

for ($i = 0; $i < (int) $nombre; $i++) {
    try {
        $bilan['numeros'][] = $service->enregistrer($vente, 1000, ModePaiement::Especes)->numero;
    } catch (OperationRefuseeException $refus) {
        $bilan['refus'][] = $refus->getMessage();
    } catch (Throwable $erreur) {
        $bilan['erreurs'][] = $erreur->getMessage();
    }
}

echo json_encode($bilan);
