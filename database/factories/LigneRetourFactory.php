<?php

namespace Database\Factories;

use App\Models\LigneRetour;
use App\Models\Produit;
use App\Models\Retour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LigneRetour>
 */
class LigneRetourFactory extends Factory
{
    public function definition(): array
    {
        $quantite = fake()->numberBetween(1, 5);
        $prixUnitaire = fake()->numberBetween(5, 2000) * 100;

        return [
            'retour_id' => Retour::factory(),
            'produit_id' => Produit::factory(),
            'quantite' => $quantite,
            'prix_unitaire' => $prixUnitaire,
            'total' => $quantite * $prixUnitaire,
        ];
    }
}
