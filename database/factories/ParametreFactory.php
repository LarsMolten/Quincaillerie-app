<?php

namespace Database\Factories;

use App\Models\Parametre;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Parametre>
 */
class ParametreFactory extends Factory
{
    public function definition(): array
    {
        return [
            'cle' => fake()->unique()->lexify('parametre_????'),
            'valeur' => fake()->word(),
        ];
    }
}
