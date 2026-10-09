<?php

namespace Tests\Feature\Produits;

use App\Models\Categorie;
use App\Models\Role;
use App\Models\Unite;
use App\Models\Utilisateur;
use Database\Seeders\DroitSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Base commune des tests du module Produits : rôles et droits réels, Responsable connecté.
 */
abstract class ProduitsTestCase extends TestCase
{
    use RefreshDatabase;

    protected Categorie $categorie;

    protected Unite $unite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed([RoleSeeder::class, DroitSeeder::class]);
        $this->actingAs($this->avecRole(RoleSeeder::RESPONSABLE));

        $this->categorie = Categorie::factory()->create(['nom' => 'Maçonnerie']);
        $this->unite = Unite::factory()->create(['nom' => 'sac', 'abreviation' => 'sac']);
    }

    protected function avecRole(string $role): Utilisateur
    {
        return Utilisateur::factory()->create(['role_id' => Role::where('nom', $role)->firstOrFail()->id]);
    }

    /** Données valides d'un formulaire produit. */
    protected function donnees(array $surcharges = []): array
    {
        return [
            'nom' => 'Ciment CEM II 42,5 – sac 50 kg',
            'categorie_id' => $this->categorie->id,
            'unite_id' => $this->unite->id,
            'prix_achat' => '33000',
            'prix_vente' => '37000',
            'stock_minimum' => '40',
            '_formulaire' => 'nouveau',
            ...$surcharges,
        ];
    }
}
