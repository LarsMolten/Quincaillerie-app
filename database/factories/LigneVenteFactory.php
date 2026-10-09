<?php

namespace Database\Factories;

use App\Models\LigneVente;
use App\Models\Produit;
use App\Models\Vente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LigneVente>
 */
class LigneVenteFactory extends Factory
{
    public function definition(): array
    {
        $quantite = fake()->numberBetween(1, 10);
        $prixAchat = fake()->numberBetween(5, 2000) * 100;
        $prixUnitaire = (int) round($prixAchat * 1.25, -2);

        return [
            'vente_id' => Vente::factory(),
            'produit_id' => Produit::factory(),
            'quantite' => $quantite,
            'prix_unitaire' => $prixUnitaire,
            'prix_achat_unitaire' => $prixAchat,
            'remise' => 0,
            'total' => $quantite * $prixUnitaire,
        ];
    }
}
