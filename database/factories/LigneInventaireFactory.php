<?php

namespace Database\Factories;

use App\Models\Inventaire;
use App\Models\LigneInventaire;
use App\Models\Produit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LigneInventaire>
 */
class LigneInventaireFactory extends Factory
{
    public function definition(): array
    {
        return [
            'inventaire_id' => Inventaire::factory(),
            'produit_id' => Produit::factory(),
            'stock_theorique' => fake()->numberBetween(0, 200),
            'stock_compte' => null,
            'ecart' => null,
        ];
    }

    /** Ligne comptée, avec l'écart correspondant. */
    public function comptee(float $stockTheorique, float $stockCompte): static
    {
        return $this->state([
            'stock_theorique' => $stockTheorique,
            'stock_compte' => $stockCompte,
            'ecart' => $stockCompte - $stockTheorique,
        ]);
    }
}
