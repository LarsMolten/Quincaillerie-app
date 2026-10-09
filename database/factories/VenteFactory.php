<?php

namespace Database\Factories;

use App\Enums\ModePaiement;
use App\Enums\StatutVente;
use App\Models\Client;
use App\Models\Utilisateur;
use App\Models\Vente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vente>
 */
class VenteFactory extends Factory
{
    public function definition(): array
    {
        $total = fake()->numberBetween(5, 1000) * 1000;

        return [
            'numero' => fake()->unique()->numerify('VTE-'.now()->year.'-#####'),
            'client_id' => Client::factory(),
            'utilisateur_id' => Utilisateur::factory(),
            'date_vente' => fake()->dateTimeBetween('-30 days'),
            'sous_total' => $total,
            'remise' => 0,
            'total' => $total,
            'montant_paye' => $total,
            'reste_a_payer' => 0,
            'mode_paiement' => ModePaiement::Especes,
            'statut' => StatutVente::Validee,
            'notes' => null,
        ];
    }

    /** Vente à crédit : il reste $reste à encaisser. */
    public function aCredit(int $reste = 50000): static
    {
        return $this->state(fn (array $attributs) => [
            'sous_total' => max((float) $attributs['total'], $reste),
            'total' => max((float) $attributs['total'], $reste),
            'montant_paye' => max((float) $attributs['total'], $reste) - $reste,
            'reste_a_payer' => $reste,
            'mode_paiement' => ModePaiement::Credit,
        ]);
    }

    public function annulee(): static
    {
        return $this->state(['statut' => StatutVente::Annulee]);
    }
}
