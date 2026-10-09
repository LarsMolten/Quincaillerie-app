<?php

namespace Database\Factories;

use App\Enums\StatutRetour;
use App\Enums\TypeRetour;
use App\Models\Achat;
use App\Models\Retour;
use App\Models\Utilisateur;
use App\Models\Vente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Retour client par défaut ; état fournisseur() pour un retour lié à un achat.
 *
 * @extends Factory<Retour>
 */
class RetourFactory extends Factory
{
    public function definition(): array
    {
        return [
            'numero' => fake()->unique()->numerify('RET-'.now()->year.'-#####'),
            'type' => TypeRetour::Client,
            'vente_id' => Vente::factory(),
            'achat_id' => null,
            'utilisateur_id' => Utilisateur::factory(),
            'date_retour' => today(),
            'total' => fake()->numberBetween(1, 100) * 1000,
            'motif' => fake()->randomElement(['Produit défectueux', 'Erreur de commande', 'Article non conforme']),
            'statut' => StatutRetour::Valide,
        ];
    }

    public function fournisseur(): static
    {
        return $this->state([
            'type' => TypeRetour::Fournisseur,
            'vente_id' => null,
            'achat_id' => Achat::factory(),
        ]);
    }

    public function annule(): static
    {
        return $this->state(['statut' => StatutRetour::Annule]);
    }
}
