<?php

return [

    /*
    | Compte administrateur créé par les seeders. Le mot de passe est obligatoire
    | et ne doit jamais être écrit dans le code : il est lu dans le fichier .env.
    */
    'admin' => [
        'nom' => env('ADMIN_NOM', 'Administrateur'),
        'email' => env('ADMIN_EMAIL', 'admin@quincaillerie.test'),
        'mot_de_passe' => env('ADMIN_PASSWORD'),
    ],

    /*
    | Données de démonstration (DemoSeeder) lancées avec « php artisan db:seed ».
    | Jamais en production.
    */
    'demo' => (bool) env('SEED_DEMO', false),

];
