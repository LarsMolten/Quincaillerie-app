<?php

namespace Database\Factories;

use App\Enums\StatutAchat;
use App\Models\Achat;
use App\Models\Fournisseur;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Achat>
 */
class AchatFactory extends Factory
{
    public function definition(): array
    {
        $total = fake()->numberBetween(10, 5000) * 1000;

        return [
            'numero' => fake()->unique()->numerify('ACH-'.now()->year.'-#####'),
            'fournisseur_id' => Fournisseur::factory(),
            'utilisateur_id' => Utilisateur::factory(),
            'date_achat' => fake()->dateTimeBetween('-30 days'),
            'total' => $total,
            'montant_paye' => $total,
            'reste_a_payer' => 0,
            'statut' => StatutAchat::Valide,
            'notes' => null,
        ];
    }

    /** Achat payé en partie : il reste $reste à régler au fournisseur. */
    public function nonSolde(int $reste = 100000): static
    {
        return $this->state(fn (array $attributs) => [
            'total' => max((float) $attributs['total'], $reste),
            'montant_paye' => max((float) $attributs['total'], $reste) - $reste,
            'reste_a_payer' => $reste,
        ]);
    }

    public function annule(): static
    {
        return $this->state(['statut' => StatutAchat::Annule]);
    }
}
