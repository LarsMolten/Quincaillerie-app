<?php

namespace Database\Factories;

use App\Models\Fournisseur;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fournisseur>
 */
class FournisseurFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nom' => fake()->company(),
            'contact' => fake()->optional()->name(),
            'telephone' => fake()->numerify('020 22 ### ##'),
            'email' => fake()->optional()->companyEmail(),
            'adresse' => fake()->optional()->address(),
            'actif' => true,
        ];
    }

    public function inactif(): static
    {
        return $this->state(['actif' => false]);
    }
}
