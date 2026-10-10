<?php

namespace Database\Seeders;

use App\Services\JournalService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Données de base indispensables au fonctionnement de l'application.
     * Les seeders sont idempotents : on peut les relancer sans créer de doublons.
     * Leur création n'est pas journalisée (installation, pas une action d'utilisateur).
     */
    public function run(): void
    {
        JournalService::sansJournal(fn () => $this->call([
            RoleSeeder::class,
            DroitSeeder::class,
            AdministrateurSeeder::class,
            CategorieSeeder::class,
            UniteSeeder::class,
            ParametreSeeder::class,
            ClientComptoirSeeder::class,
        ]));

        // Données de démonstration : SEED_DEMO=true dans .env (jamais en production).
        // Journalisées comme des actions réelles, pour que le journal d'activité ait un contenu de démonstration.
        if (config('quincaillerie.demo') && ! app()->isProduction()) {
            $this->call(DemoSeeder::class);
        }
    }
}
