<?php

use App\Http\Controllers\AchatController;
use App\Http\Controllers\Auth\ConnexionController;
use App\Http\Controllers\CategorieController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DesignSystemeController;
use App\Http\Controllers\FournisseurController;
use App\Http\Controllers\PreferenceThemeController;
use App\Http\Controllers\ProduitController;
use App\Http\Controllers\UniteController;
use App\Http\Controllers\VenteController;
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

    // Achats (« nouveau » déclaré avant /achats/{achat})
    Route::middleware('droit:achats.creer')->group(function () {
        Route::get('/achats/nouveau', [AchatController::class, 'create'])->name('achats.create');
        Route::post('/achats', [AchatController::class, 'store'])->name('achats.store');
        Route::get('/achats-produits', [AchatController::class, 'produits'])->name('achats.produits');
        Route::post('/achats/{achat}/paiements', [AchatController::class, 'paiement'])->whereNumber('achat')->name('achats.paiements.store');
    });
    Route::middleware('droit:achats.voir')->group(function () {
        Route::get('/achats', [AchatController::class, 'index'])->name('achats.index');
        Route::get('/achats/{achat}', [AchatController::class, 'show'])->whereNumber('achat')->name('achats.show');
        Route::get('/achats/{achat}/bon', [AchatController::class, 'bon'])->whereNumber('achat')->name('achats.bon');
    });
    Route::post('/achats/{achat}/annulation', [AchatController::class, 'annuler'])
        ->whereNumber('achat')->middleware('droit:achats.annuler')->name('achats.annuler');

    // Ventes (caisse ; « nouvelle » déclarée avant /ventes/{vente})
    Route::middleware('droit:ventes.creer')->group(function () {
        Route::get('/ventes/nouvelle', [VenteController::class, 'create'])->name('ventes.create');
        Route::post('/ventes', [VenteController::class, 'store'])->name('ventes.store');
        Route::get('/caisse/catalogue', [VenteController::class, 'catalogue'])->name('ventes.catalogue');
        Route::get('/caisse/clients', [VenteController::class, 'clients'])->name('ventes.clients');
    });
    Route::middleware('droit:ventes.voir')->group(function () {
        Route::get('/ventes', [VenteController::class, 'index'])->name('ventes.index');
        Route::get('/ventes/{vente}', [VenteController::class, 'show'])->whereNumber('vente')->name('ventes.show');
        Route::get('/ventes/{vente}/ticket', [VenteController::class, 'ticket'])->whereNumber('vente')->name('ventes.ticket');
    });
    Route::post('/ventes/{vente}/annulation', [VenteController::class, 'annuler'])
        ->whereNumber('vente')->middleware('droit:ventes.annuler')->name('ventes.annuler');

    // Fournisseurs
    Route::middleware('droit:fournisseurs.gerer')->group(function () {
        Route::resource('fournisseurs', FournisseurController::class)->only(['index', 'show', 'store', 'update', 'destroy'])
            ->where(['fournisseur' => '[0-9]+']);
        Route::patch('/fournisseurs/{fournisseur}/statut', [FournisseurController::class, 'statut'])->name('fournisseurs.statut');
    });

    // Clients (« Crédits » déclaré avant /clients/{client})
    Route::middleware('droit:clients.gerer')->group(function () {
        Route::get('/clients/credits', [ClientController::class, 'credits'])->name('clients.credits');
        // Identifiant numérique : /clients/historique (à venir) n'est pas pris pour une fiche
        Route::resource('clients', ClientController::class)->only(['index', 'show', 'store', 'update', 'destroy'])
            ->where(['client' => '[0-9]+']);
        Route::patch('/clients/{client}/statut', [ClientController::class, 'statut'])->name('clients.statut');
    });

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
