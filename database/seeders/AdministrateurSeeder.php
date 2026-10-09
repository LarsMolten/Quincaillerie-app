<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Utilisateur;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Crée le compte administrateur par défaut à partir du fichier .env.
 */
class AdministrateurSeeder extends Seeder
{
    public function run(): void
    {
        $config = config('quincaillerie.admin');

        if (blank($config['mot_de_passe'])) {
            throw new RuntimeException(
                'Mot de passe administrateur manquant : renseignez ADMIN_PASSWORD dans le fichier .env.'
            );
        }

        // Si le compte existe déjà, son mot de passe n'est pas réinitialisé
        Utilisateur::firstOrCreate(
            ['email' => $config['email']],
            [
                'nom' => $config['nom'],
                'password' => $config['mot_de_passe'],
                'role_id' => Role::where('nom', Role::ADMINISTRATEUR)->firstOrFail()->id,
                'actif' => true,
            ],
        );
    }
}
