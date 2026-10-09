<?php

namespace Database\Factories;

use App\Enums\SensMouvement;
use App\Enums\TypeMouvementStock;
use App\Models\MouvementStock;
use App\Models\Produit;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Mouvement de test, sans effet sur le stock du produit.
 *
 * @extends Factory<MouvementStock>
 */
class MouvementStockFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement(TypeMouvementStock::cases());
        $quantite = fake()->numberBetween(1, 20);
        $stockAvant = fake()->numberBetween(20, 200);

        return [
            'produit_id' => Produit::factory(),
            'type' => $type,
            'sens' => $type->sens(),
            'quantite' => $quantite,
            'stock_avant' => $stockAvant,
            'stock_apres' => $type->sens() === SensMouvement::Entree
                ? $stockAvant + $quantite
                : $stockAvant - $quantite,
            'utilisateur_id' => Utilisateur::factory(),
            'motif' => null,
            'reference_type' => null,
            'reference_id' => null,
        ];
    }
}
