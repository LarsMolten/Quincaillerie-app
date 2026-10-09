<?php

namespace Database\Factories;

use App\Enums\StatutInventaire;
use App\Models\Inventaire;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inventaire>
 */
class InventaireFactory extends Factory
{
    public function definition(): array
    {
        return [
            'numero' => fake()->unique()->numerify('INV-'.now()->year.'-#####'),
            'date_inventaire' => today(),
            'statut' => StatutInventaire::EnCours,
            'utilisateur_id' => Utilisateur::factory(),
            'notes' => null,
        ];
    }

    public function valide(): static
    {
        return $this->state(['statut' => StatutInventaire::Valide]);
    }
}
