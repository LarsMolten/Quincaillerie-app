<?php

namespace Tests\Feature\Seeders;

use App\Models\Categorie;
use App\Models\Client;
use App\Models\Droit;
use App\Models\Parametre;
use App\Models\Role;
use App\Models\Unite;
use App\Models\Utilisateur;
use Database\Seeders\AdministrateurSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DroitSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class DonneesDeBaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'quincaillerie.admin.mot_de_passe' => 'MotDePasseDeTest!',
            'quincaillerie.demo' => false,
        ]);
    }

    public function test_les_donnees_de_base_sont_creees(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertEqualsCanonicalizing(
            [Role::ADMINISTRATEUR, RoleSeeder::RESPONSABLE, RoleSeeder::VENDEUR, RoleSeeder::MAGASINIER],
            Role::pluck('nom')->all(),
        );
        $this->assertSame(count(DroitSeeder::DROITS), Droit::count());
        $this->assertSame(10, Categorie::count());
        $this->assertTrue(Unite::where('abreviation', 'kg')->exists());
        $this->assertSame('0', Parametre::where('cle', 'taux_tva')->value('valeur'));
        $this->assertSame('FAC', Parametre::where('cle', 'prefixe_facture')->value('valeur'));
        $this->assertSame('orange', Parametre::where('cle', 'couleur_accent')->value('valeur'));
        $this->assertTrue(Client::where('nom', Client::COMPTOIR)->exists());
    }

    public function test_les_codes_de_droits_sont_de_la_forme_module_action(): void
    {
        $this->seed([RoleSeeder::class, DroitSeeder::class]);

        foreach (Droit::all() as $droit) {
            $this->assertMatchesRegularExpression('/^[a-z]+\.[a-z]+$/', $droit->code);
            $this->assertStringStartsWith($droit->module.'.', $droit->code);
        }
    }

    public function test_association_des_droits_par_role(): void
    {
        $this->seed([RoleSeeder::class, DroitSeeder::class]);

        $codes = fn (string $role) => Role::where('nom', $role)->firstOrFail()->droits->pluck('code');

        $this->assertCount(count(DroitSeeder::DROITS), $codes(Role::ADMINISTRATEUR));

        $responsable = $codes(RoleSeeder::RESPONSABLE);
        $this->assertContains('finances.voir', $responsable);
        $this->assertContains('journal.voir', $responsable);
        $this->assertNotContains('utilisateurs.gerer', $responsable);
        $this->assertNotContains('roles.gerer', $responsable);
        $this->assertNotContains('parametres.gerer', $responsable);

        $this->assertEqualsCanonicalizing(
            ['ventes.voir', 'ventes.creer', 'clients.gerer', 'factures.voir', 'paiements.gerer', 'produits.voir'],
            $codes(RoleSeeder::VENDEUR)->all(),
        );
        $this->assertEqualsCanonicalizing(
            ['produits.voir', 'produits.creer', 'produits.modifier', 'produits.desactiver', 'stock.voir', 'stock.ajuster', 'inventaires.gerer'],
            $codes(RoleSeeder::MAGASINIER)->all(),
        );
    }

    public function test_l_administrateur_par_defaut_utilise_le_mot_de_passe_du_env(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = Utilisateur::where('email', 'admin@quincaillerie.test')->firstOrFail();

        $this->assertTrue(Hash::check('MotDePasseDeTest!', $admin->password));
        $this->assertTrue($admin->estAdministrateur());
        $this->assertTrue($admin->aDroit('parametres.gerer'));
    }

    public function test_sans_mot_de_passe_administrateur_le_seed_echoue_clairement(): void
    {
        config(['quincaillerie.admin.mot_de_passe' => null]);
        $this->seed(RoleSeeder::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ADMIN_PASSWORD');

        $this->seed(AdministrateurSeeder::class);
    }

    public function test_les_seeders_peuvent_etre_relances_sans_doublon_ni_ecraser_les_reglages(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Réglages modifiés par l'utilisateur entre deux lancements
        Parametre::where('cle', 'nom_entreprise')->update(['valeur' => 'Quincaillerie Fanantenana']);
        $vendeur = Role::where('nom', RoleSeeder::VENDEUR)->firstOrFail();
        $vendeur->droits()->attach(Droit::where('code', 'ventes.remise')->value('id'));

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(4, Role::count());
        $this->assertSame(1, Utilisateur::count());
        $this->assertSame(1, Client::where('nom', Client::COMPTOIR)->count());
        $this->assertSame('Quincaillerie Fanantenana', Parametre::where('cle', 'nom_entreprise')->value('valeur'));
        $this->assertTrue($vendeur->fresh()->droits->contains('code', 'ventes.remise'));
    }
}
