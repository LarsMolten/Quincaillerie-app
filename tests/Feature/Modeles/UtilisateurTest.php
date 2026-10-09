<?php

namespace Tests\Feature\Modeles;

use App\Models\Role;
use App\Models\Utilisateur;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UtilisateurTest extends TestCase
{
    use RefreshDatabase;

    public function test_utilisateur_est_authentifiable_et_cache_son_mot_de_passe(): void
    {
        $utilisateur = Utilisateur::factory()->create();

        $this->assertInstanceOf(Authenticatable::class, $utilisateur);
        $this->assertArrayNotHasKey('password', $utilisateur->toArray());
        $this->assertInstanceOf(Role::class, $utilisateur->role);
    }

    public function test_a_droit_selon_les_droits_de_son_role(): void
    {
        $vendeur = Utilisateur::factory()->avecDroits(['ventes.creer'])->create();

        $this->assertTrue($vendeur->aDroit('ventes.creer'));
        $this->assertFalse($vendeur->aDroit('ventes.remise'));
        $this->assertTrue($vendeur->role->droits->contains('code', 'ventes.creer'));
        $this->assertTrue($vendeur->role->utilisateurs->contains($vendeur));
    }

    public function test_l_administrateur_a_tous_les_droits(): void
    {
        $admin = Utilisateur::factory()->administrateur()->create();

        $this->assertTrue($admin->estAdministrateur());
        $this->assertTrue($admin->aDroit('stock.ajuster'));
    }

    public function test_un_compte_inactif_n_a_aucun_droit(): void
    {
        $admin = Utilisateur::factory()->administrateur()->inactif()->create();
        $vendeur = Utilisateur::factory()->inactif()->avecDroits(['ventes.creer'])->create();

        $this->assertFalse($admin->aDroit('ventes.creer'));
        $this->assertFalse($vendeur->aDroit('ventes.creer'));
    }
}
