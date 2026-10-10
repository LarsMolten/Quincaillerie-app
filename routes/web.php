<?php

use App\Http\Controllers\AchatController;
use App\Http\Controllers\Auth\ConnexionController;
use App\Http\Controllers\CategorieController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CreanceController;
use App\Http\Controllers\DepenseController;
use App\Http\Controllers\DesignSystemeController;
use App\Http\Controllers\DetteController;
use App\Http\Controllers\FactureController;
use App\Http\Controllers\FournisseurController;
use App\Http\Controllers\InventaireController;
use App\Http\Controllers\MouvementController;
use App\Http\Controllers\PaiementController;
use App\Http\Controllers\PreferenceThemeController;
use App\Http\Controllers\ProduitController;
use App\Http\Controllers\RapportController;
use App\Http\Controllers\RetourController;
use App\Http\Controllers\TableauDeBordController;
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

    // Tableau de bord : page commune à tous les comptes, chaque carte est filtrée par son droit
    Route::get('/', [TableauDeBordController::class, 'index'])->name('accueil');
    Route::get('/tableau-de-bord/{carte}', [TableauDeBordController::class, 'carte'])
        ->whereIn('carte', array_keys(TableauDeBordController::CARTES))->name('tableau-de-bord.carte');

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
    });
    Route::post('/achats/{achat}/paiements', [AchatController::class, 'paiement'])
        ->whereNumber('achat')->middleware(['droit:paiements.gerer', 'droit:achats.voir'])->name('achats.paiements.store');
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

    // Factures (jamais modifiables : aucune route d'édition ni de suppression)
    Route::middleware('droit:factures.voir')->group(function () {
        Route::get('/factures', [FactureController::class, 'index'])->name('factures.index');
        Route::get('/factures/{facture}', [FactureController::class, 'show'])->whereNumber('facture')->name('factures.show');
        Route::get('/factures/{facture}/pdf', [FactureController::class, 'pdf'])->whereNumber('facture')->name('factures.pdf');
        Route::post('/factures/{facture}/envoi', [FactureController::class, 'envoyer'])
            ->whereNumber('facture')->middleware('throttle:10,1')->name('factures.envoyer');
        Route::post('/factures/{facture}/partage', [FactureController::class, 'partager'])->whereNumber('facture')->name('factures.partager');
    });

    // Mouvements de stock : consultation (stock.voir), ajustements manuels (stock.ajuster)
    Route::middleware('droit:stock.voir')->group(function () {
        Route::get('/stock/mouvements', [MouvementController::class, 'index'])->name('stock.mouvements');
        Route::get('/stock/entrees', [MouvementController::class, 'entrees'])->name('stock.entrees');
        Route::get('/stock/sorties', [MouvementController::class, 'sorties'])->name('stock.sorties');
        Route::get('/stock/mouvements/export', [MouvementController::class, 'export'])->name('stock.mouvements.export');
    });
    Route::middleware('droit:stock.ajuster')->group(function () {
        Route::get('/stock/produits', [MouvementController::class, 'produits'])->name('stock.produits');
        Route::post('/stock/ajustements', [MouvementController::class, 'ajuster'])->name('stock.ajustements.store');
    });

    // Inventaires physiques (un inventaire validé n'est plus modifiable : aucune route de suppression)
    Route::middleware('droit:inventaires.gerer')->group(function () {
        Route::get('/inventaires', [InventaireController::class, 'index'])->name('inventaires.index');
        Route::post('/inventaires', [InventaireController::class, 'store'])->name('inventaires.store');
        Route::get('/inventaires/{inventaire}', [InventaireController::class, 'show'])->whereNumber('inventaire')->name('inventaires.show');
        Route::patch('/inventaires/{inventaire}/lignes/{ligne}', [InventaireController::class, 'compter'])
            ->whereNumber(['inventaire', 'ligne'])->scopeBindings()->name('inventaires.lignes.update');
        Route::post('/inventaires/{inventaire}/import', [InventaireController::class, 'importer'])->whereNumber('inventaire')->name('inventaires.import');
        Route::post('/inventaires/{inventaire}/validation', [InventaireController::class, 'valider'])->whereNumber('inventaire')->name('inventaires.valider');
        Route::get('/inventaires/{inventaire}/{document}', [InventaireController::class, 'pdf'])
            ->whereNumber('inventaire')->whereIn('document', ['feuille', 'rapport'])->name('inventaires.pdf');
    });

    // Retours clients et fournisseurs (« nouveau » et « documents » déclarés avant /retours/{retour})
    Route::middleware('droit:retours.gerer')->group(function () {
        Route::get('/retours/clients', [RetourController::class, 'clients'])->name('retours.clients');
        Route::get('/retours/fournisseurs', [RetourController::class, 'fournisseurs'])->name('retours.fournisseurs');
        Route::get('/retours/nouveau', [RetourController::class, 'create'])->name('retours.create');
        Route::get('/retours/documents', [RetourController::class, 'documents'])->name('retours.documents');
        Route::post('/retours', [RetourController::class, 'store'])->name('retours.store');
        Route::get('/retours/{retour}', [RetourController::class, 'show'])->whereNumber('retour')->name('retours.show');
        Route::get('/retours/{retour}/bon', [RetourController::class, 'bon'])->whereNumber('retour')->name('retours.bon');
        Route::post('/retours/{retour}/annulation', [RetourController::class, 'annuler'])->whereNumber('retour')->name('retours.annuler');
    });

    // Dépenses (suppression douce réservée à l'Administrateur : contrôlée par DepenseService)
    Route::resource('depenses', DepenseController::class)->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['depenses' => 'depense'])->whereNumber('depense')->middleware('droit:depenses.gerer');

    // Rapports (Ventes, Achats, Stock : rapports.voir ; Finances : finances.voir), exports PDF et Excel
    foreach (RapportController::RAPPORTS as $rapport => $definition) {
        Route::get("/rapports/{$rapport}", [RapportController::class, 'afficher'])
            ->defaults('rapport', $rapport)->middleware('droit:'.$definition['droit'])->name("rapports.{$rapport}");
    }
    Route::get('/rapports/{rapport}/{format}', [RapportController::class, 'export'])
        ->whereIn('rapport', array_keys(RapportController::RAPPORTS))->whereIn('format', ['pdf', 'excel'])->name('rapports.export');

    // Paiements, créances clients et reçus
    Route::middleware('droit:paiements.gerer')->group(function () {
        Route::get('/paiements', [PaiementController::class, 'index'])->name('paiements.index');
        Route::get('/paiements/{paiement}/recu', [PaiementController::class, 'recu'])->whereNumber('paiement')->name('paiements.recu');
        Route::post('/ventes/{vente}/paiements', [PaiementController::class, 'encaisser'])->whereNumber('vente')->name('paiements.ventes.store');
        Route::get('/creances', [CreanceController::class, 'index'])->name('creances.index');
        Route::get('/creances/{client}', [CreanceController::class, 'show'])->whereNumber('client')->name('creances.show');
    });
    // Ancienne page « Crédits » des clients, fusionnée dans « Créances »
    Route::permanentRedirect('/clients/credits', '/creances');

    // Dettes fournisseurs : paiements.gerer ET achats.voir
    Route::middleware(['droit:paiements.gerer', 'droit:achats.voir'])->group(function () {
        Route::get('/dettes', [DetteController::class, 'index'])->name('dettes.index');
        Route::get('/dettes/{fournisseur}', [DetteController::class, 'show'])->whereNumber('fournisseur')->name('dettes.show');
    });

    // Fournisseurs
    Route::middleware('droit:fournisseurs.gerer')->group(function () {
        Route::resource('fournisseurs', FournisseurController::class)->only(['index', 'show', 'store', 'update', 'destroy'])
            ->where(['fournisseur' => '[0-9]+']);
        Route::patch('/fournisseurs/{fournisseur}/statut', [FournisseurController::class, 'statut'])->name('fournisseurs.statut');
    });

    // Clients
    Route::middleware('droit:clients.gerer')->group(function () {
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
            ->middleware(array_map(fn (string $code) => 'droit:'.$code, (array) $entree['droit']))
            ->name($entree['route']);
    }
});

// Facture partagée (WhatsApp) : seule route publique de l'application, décision validée (exception à CLAUDE.md §6).
// Lien signé et expirant (FactureController::JOURS_PARTAGE jours), généré et journalisé par factures.partager.
Route::get('/f/{facture}', [FactureController::class, 'publique'])
    ->whereNumber('facture')->middleware(['signed', 'throttle:30,1'])->name('factures.publique');

// Vitrine des composants : environnement local uniquement (404 ailleurs)
Route::get('/design-systeme', DesignSystemeController::class)->name('design-systeme');
