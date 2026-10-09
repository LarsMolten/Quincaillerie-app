<?php

use App\Http\Controllers\Auth\ConnexionController;
use App\Http\Controllers\DesignSystemeController;
use App\Http\Controllers\PreferenceThemeController;
use App\Support\Navigation;
use Illuminate\Support\Facades\Route;

// Connexion (visiteurs uniquement)
Route::middleware('guest')->group(function () {
    Route::get('/connexion', [ConnexionController::class, 'create'])->name('connexion');
    // Limite par IP en plus de la limite par email gérée dans ConnexionRequest
    Route::post('/connexion', [ConnexionController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('connexion.valider');
    Route::view('/mot-de-passe-oublie', 'auth.mot-de-passe-oublie')->name('mot-de-passe.oublie');
});

Route::middleware('auth')->group(function () {
    Route::post('/deconnexion', [ConnexionController::class, 'destroy'])->name('deconnexion');

    // Tableau de bord provisoire (prompt 20)
    Route::view('/', 'accueil')->name('accueil');

    // Préférence personnelle de l'utilisateur connecté : aucun droit particulier requis
    Route::patch('/preferences/theme', PreferenceThemeController::class)->name('preferences.theme');

    // Modules pas encore implémentés : page « Bientôt disponible », protégée par le droit du module
    foreach (Navigation::aVenir() as $entree) {
        Route::view($entree['url'], 'bientot-disponible')
            ->middleware('droit:'.$entree['droit'])
            ->name($entree['route']);
    }
});

// Vitrine des composants : environnement local uniquement (404 ailleurs)
Route::get('/design-systeme', DesignSystemeController::class)->name('design-systeme');
