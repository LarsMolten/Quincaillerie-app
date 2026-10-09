<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public const RESPONSABLE = 'Responsable';

    public const VENDEUR = 'Vendeur/Caissier';

    public const MAGASINIER = 'Magasinier';

    public function run(): void
    {
        $roles = [
            Role::ADMINISTRATEUR => 'Accès complet à l\'application.',
            self::RESPONSABLE => 'Gestion de la boutique, sans l\'administration des comptes ni des paramètres.',
            self::VENDEUR => 'Caisse, clients, encaissements et consultation des produits.',
            self::MAGASINIER => 'Produits, stock et inventaires.',
        ];

        foreach ($roles as $nom => $description) {
            Role::firstOrCreate(['nom' => $nom], ['description' => $description]);
        }
    }
}
