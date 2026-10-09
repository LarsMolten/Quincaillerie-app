<?php

/*
 * Processus de travail du test de concurrence de la numérotation (ConcurrenceNumerotationTest).
 * Démarre l'application sur la base MySQL de test, attend l'instant de départ commun,
 * puis crée N ventes comptant d'un produit (numéros VTE et FAC). Affiche un bilan JSON.
 *
 * Usage : php ventes.php <client_id> <produit_id> <utilisateur_id> <nombre> <depart_microtime>
 */

use App\Enums\ModePaiement;
use App\Models\Client;
use App\Models\Utilisateur;
use App\Services\VenteService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

[, $clientId, $produitId, $utilisateurId, $nombre, $depart] = $argv;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

config(['database.default' => 'mysql_test']);
DB::purge('mysql_test');

Auth::setUser(Utilisateur::findOrFail((int) $utilisateurId));
$service = $app->make(VenteService::class);
$client = Client::findOrFail((int) $clientId);

while (microtime(true) < (float) $depart) {
    usleep(1000);
}

$bilan = ['numeros' => [], 'factures' => [], 'erreurs' => []];

for ($i = 0; $i < (int) $nombre; $i++) {
    try {
        $vente = $service->creer($client, [['produit_id' => (int) $produitId, 'quantite' => 1]], 0, ModePaiement::Especes, 1000000);
        $bilan['numeros'][] = $vente->numero;
        $bilan['factures'][] = $vente->facture->numero;
    } catch (Throwable $erreur) {
        $bilan['erreurs'][] = $erreur->getMessage();
    }
}

echo json_encode($bilan);
