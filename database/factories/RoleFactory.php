<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nom' => ucfirst(fake()->unique()->words(2, true)),
            'description' => fake()->optional()->sentence(),
        ];
    }

    public function administrateur(): static
    {
        return $this->state(['nom' => Role::ADMINISTRATEUR]);
    }
}
