<?php

namespace Database\Factories;

use App\Enums\CategorieDepense;
use App\Enums\ModePaiement;
use App\Models\Depense;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Depense>
 */
class DepenseFactory extends Factory
{
    public function definition(): array
    {
        $categorie = fake()->randomElement(CategorieDepense::cases());

        return [
            'categorie' => $categorie,
            'libelle' => $categorie->libelle().' '.fake()->monthName(),
            'montant' => fake()->numberBetween(5, 500) * 1000,
            'date_depense' => fake()->dateTimeBetween('-30 days'),
            'mode_paiement' => fake()->randomElement(ModePaiement::encaissements()),
            'utilisateur_id' => Utilisateur::factory(),
            'notes' => null,
        ];
    }
}
