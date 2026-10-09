<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nom' => fake()->name(),
            'telephone' => fake()->numerify('03# ## ### ##'),
            'email' => fake()->optional()->safeEmail(),
            'adresse' => fake()->optional()->address(),
            'plafond_credit' => 500000,
            'actif' => true,
        ];
    }

    public function comptoir(): static
    {
        return $this->state([
            'nom' => Client::COMPTOIR,
            'telephone' => null,
            'email' => null,
            'adresse' => null,
            'plafond_credit' => null,
        ]);
    }

    public function inactif(): static
    {
        return $this->state(['actif' => false]);
    }
}
