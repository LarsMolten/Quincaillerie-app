<?php

namespace App\Providers;

use App\Models\Achat;
use App\Models\Client;
use App\Models\Depense;
use App\Models\Facture;
use App\Models\Fournisseur;
use App\Models\Inventaire;
use App\Models\Paiement;
use App\Models\Produit;
use App\Models\Retour;
use App\Models\Utilisateur;
use App\Models\Vente;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Noms courts et stables en base pour les relations polymorphes
        // (paiements.payable_type, mouvements_stock.reference_type)
        Relation::enforceMorphMap([
            'vente' => Vente::class,
            'achat' => Achat::class,
            'retour' => Retour::class,
            'inventaire' => Inventaire::class,
            'facture' => Facture::class,
            'paiement' => Paiement::class,
            'depense' => Depense::class,
            'produit' => Produit::class,
            'client' => Client::class,
            'fournisseur' => Fournisseur::class,
            'utilisateur' => Utilisateur::class,
        ]);
    }
}
