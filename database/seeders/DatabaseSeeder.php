<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Données de base indispensables au fonctionnement de l'application.
     * Les seeders sont idempotents : on peut les relancer sans créer de doublons.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            DroitSeeder::class,
            AdministrateurSeeder::class,
            CategorieSeeder::class,
            UniteSeeder::class,
            ParametreSeeder::class,
            ClientComptoirSeeder::class,
        ]);

        // Données de démonstration : SEED_DEMO=true dans .env (jamais en production)
        if (config('quincaillerie.demo') && ! app()->isProduction()) {
            $this->call(DemoSeeder::class);
        }
    }
}
