<?php

namespace Database\Factories;

use App\Models\Categorie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Categorie>
 */
class CategorieFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nom' => ucfirst(fake()->unique()->words(2, true)),
            'description' => fake()->optional()->sentence(),
            'actif' => true,
        ];
    }

    public function inactif(): static
    {
        return $this->state(['actif' => false]);
    }
}
