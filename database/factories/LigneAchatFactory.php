<?php

namespace Database\Factories;

use App\Models\Achat;
use App\Models\LigneAchat;
use App\Models\Produit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LigneAchat>
 */
class LigneAchatFactory extends Factory
{
    public function definition(): array
    {
        $quantite = fake()->numberBetween(1, 50);
        $prixAchat = fake()->numberBetween(5, 2000) * 100;

        return [
            'achat_id' => Achat::factory(),
            'produit_id' => Produit::factory(),
            'quantite' => $quantite,
            'prix_achat' => $prixAchat,
            'total' => $quantite * $prixAchat,
        ];
    }
}
