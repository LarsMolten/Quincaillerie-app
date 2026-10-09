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
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
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
        // Droits « module.action » : l'Administrateur actif a tout, les autres selon leur rôle.
        // Toute autre capacité (policies futures) est laissée aux règles classiques.
        Gate::before(function (Utilisateur $utilisateur, string $capacite) {
            if ($utilisateur->actif && $utilisateur->estAdministrateur()) {
                return true;
            }

            return preg_match('/^[a-z_]+\.[a-z_]+$/', $capacite) ? $utilisateur->aDroit($capacite) : null;
        });

        // @droit('ventes.remise') … @enddroit
        Blade::if('droit', fn (string $code) => (bool) auth()->user()?->can($code));

        // Pagination au design du thème, en français
        Paginator::defaultView('vendor.pagination.tailwind');
        Paginator::defaultSimpleView('vendor.pagination.simple-tailwind');

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
