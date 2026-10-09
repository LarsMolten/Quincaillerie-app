<?php

namespace Database\Factories;

use App\Models\Droit;
use App\Models\Role;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Utilisateur>
 */
class UtilisateurFactory extends Factory
{
    /** Mot de passe haché une seule fois pour accélérer les tests. */
    protected static ?string $motDePasse;

    public function definition(): array
    {
        return [
            'nom' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'telephone' => fake()->numerify('034 ## ### ##'),
            'password' => static::$motDePasse ??= Hash::make('password'),
            'role_id' => Role::factory(),
            'actif' => true,
            'preference_theme' => 'auto',
            'remember_token' => Str::random(10),
        ];
    }

    public function inactif(): static
    {
        return $this->state(['actif' => false]);
    }

    public function administrateur(): static
    {
        return $this->state(fn () => [
            'role_id' => Role::firstOrCreate(['nom' => Role::ADMINISTRATEUR])->id,
        ]);
    }

    /**
     * Donne au rôle de l'utilisateur les droits indiqués (créés s'ils n'existent pas).
     *
     * @param  list<string>  $codes  ex. ['ventes.creer', 'ventes.remise']
     */
    public function avecDroits(array $codes): static
    {
        return $this->afterCreating(function (Utilisateur $utilisateur) use ($codes) {
            $droits = collect($codes)->map(fn (string $code) => Droit::firstOrCreate(
                ['code' => $code],
                ['libelle' => $code, 'module' => Str::before($code, '.')],
            ));

            $utilisateur->role->droits()->syncWithoutDetaching($droits->pluck('id'));
        });
    }
}
