<?php

namespace Database\Factories;

use App\Enums\StatutFacture;
use App\Models\Facture;
use App\Models\Vente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Facture>
 */
class FactureFactory extends Factory
{
    public function definition(): array
    {
        return [
            'numero' => fake()->unique()->numerify('FAC-'.now()->year.'-#####'),
            'vente_id' => Vente::factory(),
            'date_emission' => now(),
            // Même montant que la vente facturée
            'total' => fn (array $attributs) => Vente::find($attributs['vente_id'])->total,
            'statut' => StatutFacture::Emise,
        ];
    }

    public function annulee(): static
    {
        return $this->state(['statut' => StatutFacture::Annulee]);
    }
}
