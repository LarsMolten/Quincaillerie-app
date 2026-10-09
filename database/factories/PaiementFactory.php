<?php

namespace Database\Factories;

use App\Enums\ModePaiement;
use App\Models\Achat;
use App\Models\Paiement;
use App\Models\Utilisateur;
use App\Models\Vente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Paiement>
 */
class PaiementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'payable_type' => 'vente',
            'payable_id' => Vente::factory(),
            'montant' => fake()->numberBetween(1, 500) * 1000,
            'mode' => fake()->randomElement(ModePaiement::encaissements()),
            'reference' => null,
            'date_paiement' => now(),
            'utilisateur_id' => Utilisateur::factory(),
        ];
    }

    /** Règlement d'un achat fournisseur. */
    public function pourAchat(): static
    {
        return $this->state([
            'payable_type' => 'achat',
            'payable_id' => Achat::factory(),
        ]);
    }
}
