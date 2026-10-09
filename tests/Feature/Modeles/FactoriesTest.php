<?php

namespace Tests\Feature\Modeles;

use App\Models\Achat;
use App\Models\Categorie;
use App\Models\Client;
use App\Models\Depense;
use App\Models\Droit;
use App\Models\Facture;
use App\Models\Fournisseur;
use App\Models\Inventaire;
use App\Models\JournalActivite;
use App\Models\LigneAchat;
use App\Models\LigneInventaire;
use App\Models\LigneRetour;
use App\Models\LigneVente;
use App\Models\MouvementStock;
use App\Models\Paiement;
use App\Models\Parametre;
use App\Models\Produit;
use App\Models\Retour;
use App\Models\Role;
use App\Models\Unite;
use App\Models\Utilisateur;
use App\Models\Vente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FactoriesTest extends TestCase
{
    use RefreshDatabase;

    public static function modeles(): array
    {
        $tables = [
            Utilisateur::class => 'utilisateurs',
            Role::class => 'roles',
            Droit::class => 'droits',
            Categorie::class => 'categories',
            Unite::class => 'unites',
            Produit::class => 'produits',
            Fournisseur::class => 'fournisseurs',
            Client::class => 'clients',
            Achat::class => 'achats',
            LigneAchat::class => 'lignes_achat',
            Vente::class => 'ventes',
            LigneVente::class => 'lignes_vente',
            Facture::class => 'factures',
            Paiement::class => 'paiements',
            MouvementStock::class => 'mouvements_stock',
            Inventaire::class => 'inventaires',
            LigneInventaire::class => 'lignes_inventaire',
            Depense::class => 'depenses',
            Retour::class => 'retours',
            LigneRetour::class => 'lignes_retour',
            Parametre::class => 'parametres',
            JournalActivite::class => 'journal_activites',
        ];

        return collect($tables)
            ->mapWithKeys(fn (string $table, string $modele) => [class_basename($modele) => [$modele, $table]])
            ->all();
    }

    #[DataProvider('modeles')]
    public function test_chaque_modele_a_une_table_francaise_et_une_factory(string $modele, string $table): void
    {
        $instance = $modele::factory()->create();

        $this->assertSame($table, $instance->getTable());
        $this->assertModelExists($instance);
    }
}
