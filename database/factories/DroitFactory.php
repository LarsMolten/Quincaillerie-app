<?php

namespace Database\Factories;

use App\Models\Droit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Droit>
 */
class DroitFactory extends Factory
{
    public function definition(): array
    {
        $module = fake()->randomElement(['produits', 'ventes', 'achats', 'stock', 'clients', 'finances']);
        $action = fake()->unique()->lexify('action_????');

        return [
            'code' => "{$module}.{$action}",
            'libelle' => ucfirst(str_replace('_', ' ', $action)),
            'module' => $module,
        ];
    }
}
