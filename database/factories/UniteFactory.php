<?php

namespace Database\Factories;

use App\Models\Unite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unite>
 */
class UniteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nom' => fake()->unique()->word(),
            'abreviation' => fake()->unique()->lexify('???'),
        ];
    }
}
