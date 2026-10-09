<?php

use App\Http\Controllers\DesignSystemeController;
use App\Http\Controllers\PreferenceThemeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('accueil');
})->name('accueil');

// Vitrine des composants : environnement local uniquement (404 ailleurs)
Route::get('/design-systeme', DesignSystemeController::class)->name('design-systeme');

// Préférence personnelle de l'utilisateur connecté : aucun droit particulier requis
Route::patch('/preferences/theme', PreferenceThemeController::class)
    ->middleware('auth')
    ->name('preferences.theme');
