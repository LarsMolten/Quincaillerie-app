<?php

namespace Database\Factories;

use App\Models\JournalActivite;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalActivite>
 */
class JournalActiviteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'utilisateur_id' => Utilisateur::factory(),
            'action' => 'connexion',
            'modele' => null,
            'modele_id' => null,
            'details' => null,
            'adresse_ip' => fake()->ipv4(),
        ];
    }
}
