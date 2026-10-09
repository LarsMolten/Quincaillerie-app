<?php

namespace Database\Factories;

use App\Models\Categorie;
use App\Models\Produit;
use App\Models\Unite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Produit de test. Le stock initial est posé directement ici (données de test uniquement) ;
 * dans l'application, seul MouvementStockService modifie stock_actuel.
 *
 * @extends Factory<Produit>
 */
class ProduitFactory extends Factory
{
    public function definition(): array
    {
        $prixAchat = fake()->numberBetween(5, 2000) * 100;

        return [
            'reference' => fake()->unique()->numerify('PRD-#####'),
            'code_barres' => fake()->unique()->ean13(),
            'nom' => fake()->randomElement([
                'Ciment', 'Tôle ondulée', 'Fer à béton', 'Clou', 'Vis à bois', 'Peinture acrylique',
                'Tuyau PVC', 'Robinet', 'Câble électrique', 'Marteau', 'Pelle', 'Brouette',
            ]).' '.fake()->numerify('###'),
            'description' => fake()->optional()->sentence(),
            'categorie_id' => Categorie::factory(),
            'unite_id' => Unite::factory(),
            'prix_achat' => $prixAchat,
            'prix_vente' => (int) round($prixAchat * fake()->randomFloat(2, 1.15, 1.4), -2),
            'prix_gros' => null,
            'stock_actuel' => fake()->numberBetween(20, 200),
            'stock_minimum' => 10,
            'actif' => true,
        ];
    }

    public function enRupture(): static
    {
        return $this->state(['stock_actuel' => 0]);
    }

    public function stockFaible(): static
    {
        return $this->state(['stock_actuel' => 3, 'stock_minimum' => 10]);
    }

    public function inactif(): static
    {
        return $this->state(['actif' => false]);
    }
}
