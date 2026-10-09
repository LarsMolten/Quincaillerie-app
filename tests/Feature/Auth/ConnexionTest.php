<?php

namespace Tests\Feature\Auth;

use App\Models\JournalActivite;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnexionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function utilisateur(array $attributs = []): Utilisateur
    {
        return Utilisateur::factory()->create(['email' => 'vendeur@quincaillerie.test', ...$attributs]);
    }

    public function test_la_page_de_connexion_s_affiche_en_francais(): void
    {
        $this->get(route('connexion'))
            ->assertOk()
            ->assertSee('Connexion')
            ->assertSee('Adresse email')
            ->assertSee('Afficher le mot de passe')
            ->assertSee('Se souvenir de moi')
            ->assertSee('Mot de passe oublié ?');
    }

    public function test_un_visiteur_est_redirige_vers_la_connexion(): void
    {
        $this->get(route('accueil'))->assertRedirect(route('connexion'));
        $this->get(route('utilisateurs.index'))->assertRedirect(route('connexion'));
    }

    public function test_connexion_reussie_et_journalisee(): void
    {
        $utilisateur = $this->utilisateur();

        $this->post(route('connexion.valider'), ['email' => 'vendeur@quincaillerie.test', 'password' => 'password'])
            ->assertRedirect(route('accueil'));

        $this->assertAuthenticatedAs($utilisateur);
        $this->assertDatabaseHas('journal_activites', [
            'utilisateur_id' => $utilisateur->id,
            'action' => 'connexion',
            'adresse_ip' => '127.0.0.1',
        ]);
    }

    public function test_un_mauvais_mot_de_passe_est_refuse_en_francais(): void
    {
        $this->utilisateur();

        $this->from(route('connexion'))
            ->post(route('connexion.valider'), ['email' => 'vendeur@quincaillerie.test', 'password' => 'faux'])
            ->assertRedirect(route('connexion'))
            ->assertSessionHasErrors(['email' => __('auth.failed')]);

        $this->assertGuest();
        $this->assertStringNotContainsString('These credentials', __('auth.failed'));
    }

    public function test_les_champs_sont_obligatoires(): void
    {
        $this->post(route('connexion.valider'), [])
            ->assertSessionHasErrors([
                'email' => 'Le champ adresse email est obligatoire.',
                'password' => 'Le champ mot de passe est obligatoire.',
            ]);
    }

    public function test_un_utilisateur_inactif_ne_peut_pas_se_connecter(): void
    {
        $utilisateur = $this->utilisateur(['actif' => false]);

        $this->from(route('connexion'))
            ->post(route('connexion.valider'), ['email' => 'vendeur@quincaillerie.test', 'password' => 'password'])
            ->assertRedirect(route('connexion'))
            ->assertSessionHas('erreur', 'Votre compte est désactivé. Contactez l\'administrateur.');

        $this->assertGuest();
        $this->assertDatabaseHas('journal_activites', ['utilisateur_id' => $utilisateur->id, 'action' => 'connexion.refusee']);

        // Le message est affiché en toast sur la page de connexion
        $this->get(route('connexion'))->assertSee('Votre compte est désactivé', false);
    }

    public function test_un_compte_supprime_ne_peut_pas_se_connecter(): void
    {
        $this->utilisateur()->delete();

        $this->post(route('connexion.valider'), ['email' => 'vendeur@quincaillerie.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_les_tentatives_sont_limitees(): void
    {
        $this->utilisateur();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('connexion.valider'), ['email' => 'vendeur@quincaillerie.test', 'password' => 'faux']);
        }

        // Même avec le bon mot de passe, le compte est temporairement bloqué
        $this->post(route('connexion.valider'), ['email' => 'vendeur@quincaillerie.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertStringContainsString('Tentatives de connexion trop nombreuses', session('errors')->first('email'));
    }

    public function test_deconnexion(): void
    {
        $utilisateur = $this->utilisateur();

        $this->actingAs($utilisateur)
            ->post(route('deconnexion'))
            ->assertRedirect(route('connexion'))
            ->assertSessionHas('info', 'Vous êtes déconnecté.');

        $this->assertGuest();
        $this->assertSame('deconnexion', JournalActivite::latest('id')->value('action'));
    }

    public function test_un_utilisateur_connecte_ne_revoit_pas_la_connexion(): void
    {
        $this->actingAs($this->utilisateur())
            ->get(route('connexion'))
            ->assertRedirect(route('accueil'));
    }

    public function test_mot_de_passe_oublie_renvoie_vers_l_administrateur(): void
    {
        $this->get(route('mot-de-passe.oublie'))
            ->assertOk()
            ->assertSee('seul un administrateur peut réinitialiser votre mot de passe');
    }
}
