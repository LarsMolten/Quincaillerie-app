<?php

use App\Http\Controllers\Auth\ConnexionController;
use App\Http\Controllers\CategorieController;
use App\Http\Controllers\DesignSystemeController;
use App\Http\Controllers\PreferenceThemeController;
use App\Http\Controllers\ProduitController;
use App\Http\Controllers\UniteController;
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

    // Produits : jamais de suppression, seulement désactivation
    Route::middleware('droit:produits.voir')->group(function () {
        Route::get('/produits', [ProduitController::class, 'index'])->name('produits.index');
        Route::get('/produits/etiquettes', [ProduitController::class, 'etiquettes'])->name('produits.etiquettes');
        Route::get('/produits/{produit}', [ProduitController::class, 'show'])->name('produits.show');
        Route::get('/produits/{produit}/photo', [ProduitController::class, 'photo'])->name('produits.photo');
    });
    Route::middleware('droit:produits.creer')->group(function () {
        Route::post('/produits', [ProduitController::class, 'store'])->name('produits.store');
        Route::get('/produits-code-barres', [ProduitController::class, 'codeBarres'])->name('produits.code-barres');
    });
    Route::put('/produits/{produit}', [ProduitController::class, 'update'])
        ->middleware('droit:produits.modifier')->name('produits.update');
    Route::patch('/produits/{produit}/statut', [ProduitController::class, 'statut'])
        ->middleware('droit:produits.desactiver')->name('produits.statut');

    // Catégories et unités
    Route::middleware('droit:categories.gerer')->group(function () {
        Route::resource('categories', CategorieController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['categories' => 'categorie']);
        Route::patch('/categories/{categorie}/statut', [CategorieController::class, 'statut'])->name('categories.statut');
        Route::resource('unites', UniteController::class)->only(['index', 'store', 'update', 'destroy']);
    });

    // Modules pas encore implémentés : page « Bientôt disponible », protégée par le droit du module
    foreach (Navigation::aVenir() as $entree) {
        Route::view($entree['url'], 'bientot-disponible')
            ->middleware('droit:'.$entree['droit'])
            ->name($entree['route']);
    }
});

// Vitrine des composants : environnement local uniquement (404 ailleurs)
Route::get('/design-systeme', DesignSystemeController::class)->name('design-systeme');
