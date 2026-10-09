<?php

namespace Database\Seeders;

use App\Models\Droit;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Crée les droits « module.action » et les associe aux rôles par défaut.
 * À lancer après RoleSeeder.
 */
class DroitSeeder extends Seeder
{
    /** Code => libellé affiché dans l'écran « Rôles et droits ». */
    public const DROITS = [
        'produits.voir' => 'Voir les produits',
        'produits.creer' => 'Créer des produits',
        'produits.modifier' => 'Modifier des produits',
        'produits.desactiver' => 'Désactiver des produits',
        'categories.gerer' => 'Gérer les catégories et unités',
        'fournisseurs.gerer' => 'Gérer les fournisseurs',
        'clients.gerer' => 'Gérer les clients',
        'achats.voir' => 'Voir les achats',
        'achats.creer' => 'Enregistrer des achats',
        'achats.annuler' => 'Annuler des achats',
        'ventes.voir' => 'Voir les ventes',
        'ventes.creer' => 'Enregistrer des ventes (caisse)',
        'ventes.annuler' => 'Annuler des ventes',
        'ventes.remise' => 'Accorder des remises',
        'stock.voir' => 'Voir le stock et les mouvements',
        'stock.ajuster' => 'Ajuster le stock',
        'inventaires.gerer' => 'Gérer les inventaires',
        'retours.gerer' => 'Gérer les retours',
        'paiements.gerer' => 'Gérer les paiements',
        'factures.voir' => 'Voir et imprimer les factures',
        'depenses.gerer' => 'Gérer les dépenses',
        'rapports.voir' => 'Voir les rapports',
        'finances.voir' => 'Voir les données financières (bénéfice, achats)',
        'utilisateurs.gerer' => 'Gérer les utilisateurs',
        'roles.gerer' => 'Gérer les rôles et droits',
        'parametres.gerer' => 'Gérer les paramètres',
        'journal.voir' => 'Consulter le journal d\'activité',
    ];

    public function run(): void
    {
        foreach (self::DROITS as $code => $libelle) {
            Droit::updateOrCreate(
                ['code' => $code],
                ['libelle' => $libelle, 'module' => Str::before($code, '.')],
            );
        }

        $tous = array_keys(self::DROITS);
        $reserveAdministration = ['utilisateurs', 'roles', 'parametres'];

        $droitsParRole = [
            Role::ADMINISTRATEUR => $tous,
            RoleSeeder::RESPONSABLE => array_values(array_filter(
                $tous,
                fn (string $code) => ! in_array(Str::before($code, '.'), $reserveAdministration, true),
            )),
            RoleSeeder::VENDEUR => [
                'ventes.voir', 'ventes.creer', 'clients.gerer', 'factures.voir', 'paiements.gerer', 'produits.voir',
            ],
            RoleSeeder::MAGASINIER => array_values(array_filter(
                $tous,
                fn (string $code) => in_array(Str::before($code, '.'), ['produits', 'stock', 'inventaires'], true),
            )),
        ];

        foreach ($droitsParRole as $nomRole => $codes) {
            $role = Role::where('nom', $nomRole)->firstOrFail();
            $ids = Droit::whereIn('code', $codes)->pluck('id');

            // L'Administrateur reçoit toujours tous les droits. Pour les autres rôles, les droits
            // par défaut ne sont posés qu'à la création, pour ne pas écraser les réglages faits ensuite.
            if ($role->estAdministrateur() || $role->droits()->doesntExist()) {
                $role->droits()->sync($ids);
            }
        }
    }
}
