<?php

/*
 * Processus de travail du test de concurrence de la numérotation (ConcurrenceNumerotationTest).
 * Démarre l'application sur la base MySQL de test, attend l'instant de départ commun,
 * puis crée N achats d'un produit. Affiche un bilan JSON.
 *
 * Usage : php achats.php <fournisseur_id> <produit_id> <utilisateur_id> <nombre> <depart_microtime>
 */

use App\Models\Fournisseur;
use App\Models\Utilisateur;
use App\Services\AchatService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

[, $fournisseurId, $produitId, $utilisateurId, $nombre, $depart] = $argv;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

config(['database.default' => 'mysql_test']);
DB::purge('mysql_test');

Auth::setUser(Utilisateur::findOrFail((int) $utilisateurId));
$service = $app->make(AchatService::class);
$fournisseur = Fournisseur::findOrFail((int) $fournisseurId);

while (microtime(true) < (float) $depart) {
    usleep(1000);
}

$bilan = ['numeros' => [], 'erreurs' => []];

for ($i = 0; $i < (int) $nombre; $i++) {
    try {
        $achat = $service->creer($fournisseur, [['produit_id' => (int) $produitId, 'quantite' => 1, 'prix_achat' => 1000]]);
        $bilan['numeros'][] = $achat->numero;
    } catch (Throwable $erreur) {
        $bilan['erreurs'][] = $erreur->getMessage();
    }
}

echo json_encode($bilan);
