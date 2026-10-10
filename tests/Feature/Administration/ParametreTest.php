<?php

namespace Tests\Feature\Administration;

use App\Models\Achat;
use App\Models\JournalActivite;
use App\Models\Parametre;
use App\Models\Role;
use App\Models\Utilisateur;
use App\Services\FactureService;
use App\Services\NumerotationService;
use App\Support\Entreprise;
use Database\Seeders\DroitSeeder;
use Database\Seeders\ParametreSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Administration > Paramètres : onglets Entreprise (logo), Facturation (préfixes, TVA, format),
 * Ventes (remise par rôle, stock négatif), Apparence (thème par défaut, couleur d'accent écran et PDF).
 */
class ParametreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed([RoleSeeder::class, DroitSeeder::class, ParametreSeeder::class]);
        $this->actingAs(Utilisateur::factory()->create(['role_id' => Role::where('nom', Role::ADMINISTRATEUR)->value('id')]));
    }

    private function valeur(string $cle): ?string
    {
        Parametre::viderCache();

        return Parametre::valeur($cle);
    }

    public function test_onglets_et_droit(): void
    {
        $this->get(route('parametres.index'))->assertOk()
            ->assertSee('Entreprise')->assertSee('Facturation')->assertSee('Apparence')->assertSee('Nom de l&#039;entreprise', false)
            ->assertSee('aria-current="page"', false);
        $this->get(route('parametres.index', 'facturation'))->assertOk()->assertSee('VTE-'.now()->year.'-00001')->assertSee('repartir la numérotation');
        $this->get(route('parametres.index', 'ventes'))->assertOk()->assertSee('Remise maximale par rôle')->assertSee('Sans droit de remise');
        $this->get(route('parametres.index', 'apparence'))->assertOk()->assertSee('Orange sécurité')->assertSee('data-accent="violet"', false);
        $this->get('/parametres/inconnu')->assertNotFound();

        $this->actingAs(Utilisateur::factory()->create(['role_id' => Role::where('nom', RoleSeeder::RESPONSABLE)->value('id')]))
            ->get(route('parametres.index'))->assertForbidden();
    }

    public function test_entreprise_et_logo(): void
    {
        Storage::fake('local');

        $this->put(route('parametres.update', 'entreprise'), [
            'nom_entreprise' => ' Quincaillerie Rova ', 'adresse' => 'Analakely', 'telephone' => '020 22 123 45', 'email' => 'contact@rova.mg', 'nif_stat' => 'NIF 123',
            'logo' => UploadedFile::fake()->image('logo.png', 300, 120),
        ])->assertRedirect(route('parametres.index', 'entreprise'))->assertSessionHas('succes', 'Paramètres « Entreprise » enregistrés.');

        $this->assertSame('Quincaillerie Rova', $this->valeur('nom_entreprise'));
        $logo = $this->valeur('logo');
        Storage::disk('local')->assertExists($logo);
        $this->assertStringStartsWith('data:image/png;base64,', Entreprise::donnees()['logo']);
        $this->get(route('parametres.logo'))->assertOk();
        $this->assertDatabaseHas('journal_activites', ['action' => 'parametre.modifie', 'modele_id' => Parametre::where('cle', 'nom_entreprise')->value('id')]);

        $this->put(route('parametres.update', 'entreprise'), ['nom_entreprise' => 'Quincaillerie Rova', 'retirer_logo' => '1'])->assertSessionHas('succes');
        Storage::disk('local')->assertMissing($logo);
        $this->assertSame('', (string) $this->valeur('logo'));
        $this->get(route('parametres.logo'))->assertNotFound();

        $this->put(route('parametres.update', 'entreprise'), ['nom_entreprise' => '', 'email' => 'pas-un-email', 'logo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors(['nom_entreprise' => 'Le champ nom de l\'entreprise est obligatoire.', 'email', 'logo']);
    }

    public function test_facturation_prefixes_tva_et_format(): void
    {
        $donnees = ['prefixe_facture' => 'fact', 'prefixe_vente' => 'VNT', 'prefixe_achat' => 'ACH', 'prefixe_recu' => 'REC', 'prefixe_retour' => 'RET',
            'prefixe_inventaire' => 'INV', 'taux_tva' => '20', 'format_facture' => 'ticket', 'pied_de_facture' => 'Merci !'];

        $this->put(route('parametres.update', 'facturation'), $donnees)->assertSessionHas('succes');
        $this->assertSame(['FACT', 'VNT', '20', 'ticket'], [$this->valeur('prefixe_facture'), $this->valeur('prefixe_vente'), $this->valeur('taux_tva'), $this->valeur('format_facture')]);
        $this->assertSame(FactureService::FORMAT_TICKET, FactureService::formatParDefaut());

        // Les nouveaux documents prennent le nouveau préfixe (séquence propre au préfixe)
        $numerotation = app(NumerotationService::class);
        $this->assertSame('VNT', $numerotation->prefixe('vente'));
        DB::transaction(fn () => $this->assertSame('VNT-'.now()->year.'-00001', $numerotation->suivant(Achat::class, $numerotation->prefixe('vente'), now())));

        // Préfixes : format et unicité
        $this->put(route('parametres.update', 'facturation'), [...$donnees, 'prefixe_vente' => 'V1', 'prefixe_achat' => 'FACT'])
            ->assertSessionHasErrors([
                'prefixe_vente' => 'Le préfixe (ventes) doit contenir 2 à 5 lettres majuscules (ex. FAC).',
                'prefixe_achat' => 'Ce préfixe est déjà utilisé par un autre type de document.',
            ]);
        $this->put(route('parametres.update', 'facturation'), [...$donnees, 'taux_tva' => '150', 'format_facture' => 'a5'])->assertSessionHasErrors(['taux_tva', 'format_facture']);
    }

    public function test_ventes_remise_par_role_et_stock_negatif(): void
    {
        $vendeur = Role::where('nom', RoleSeeder::VENDEUR)->firstOrFail();
        $responsable = Role::where('nom', RoleSeeder::RESPONSABLE)->firstOrFail();

        $this->put(route('parametres.update', 'ventes'), [
            'remise_max_pourcentage' => '8', 'remises' => [$vendeur->id => '2,5', $responsable->id => ''], 'stock_negatif_autorise' => '1',
        ])->assertSessionHas('succes', 'Paramètres « Ventes » enregistrés.');

        $this->assertSame('8', $this->valeur('remise_max_pourcentage'));
        $this->assertTrue(Parametre::actif('stock_negatif_autorise'));
        $this->assertSame(2.5, $vendeur->fresh()->plafondRemise());
        $this->assertNull($responsable->fresh()->remise_max);
        $this->assertSame(8.0, $responsable->fresh()->plafondRemise(), 'Sans remise propre : la remise générale.');
        $this->assertDatabaseHas('journal_activites', ['action' => 'role.modifie', 'modele_id' => $vendeur->id]);

        $this->put(route('parametres.update', 'ventes'), ['remise_max_pourcentage' => '120', 'remises' => [$vendeur->id => '-1']])
            ->assertSessionHasErrors(['remise_max_pourcentage', 'remises.'.$vendeur->id]);
    }

    public function test_onglet_ventes_tout_ou_rien(): void
    {
        $vendeur = Role::where('nom', RoleSeeder::VENDEUR)->firstOrFail();
        // Échec à l'écriture des remises par rôle (ex. base non migrée : colonne remise_max absente)
        Role::saving(fn () => throw new \RuntimeException('Échec simulé'));

        $this->withoutExceptionHandling();
        try {
            $this->put(route('parametres.update', 'ventes'), ['remise_max_pourcentage' => '8', 'remises' => [$vendeur->id => '3'], 'stock_negatif_autorise' => '1']);
            $this->fail('Échec attendu.');
        } catch (\RuntimeException $erreur) {
            $this->assertSame('Échec simulé', $erreur->getMessage());
        }

        // Rien n'est enregistré à moitié : la remise générale et le stock négatif restent inchangés
        $this->assertSame('10', $this->valeur('remise_max_pourcentage'));
        $this->assertFalse(Parametre::actif('stock_negatif_autorise'));
        $this->assertSame(0, JournalActivite::where('action', 'parametre.modifie')->count());
    }

    public function test_apparence_theme_par_defaut_et_accent_ecran_et_pdf(): void
    {
        $this->put(route('parametres.update', 'apparence'), ['theme_defaut' => 'sombre', 'couleur_accent' => 'bleu'])->assertSessionHas('succes');

        // Écran : data-accent sur <html> ; thème par défaut lu par le script anti-flash (page de connexion)
        $this->get(route('parametres.index', 'apparence'))->assertSee('<html lang="fr" data-accent="bleu">', false);
        auth()->logout();
        $this->get(route('connexion'))->assertSee('var defaut = "sombre";', false)->assertSee('data-accent="bleu"', false);

        // PDF : palette hexadécimale de l'accent
        $html = view('produits.etiquettes', ['pages' => collect(), 'entreprise' => 'Rova'])->render();
        $this->assertStringContainsString('#1570d1', $html);
        $this->assertStringNotContainsString('#fe7802', $html);

        $journal = JournalActivite::where('action', 'parametre.modifie')->latest('id')->firstOrFail();
        $this->assertSame(['orange', 'bleu'], [$journal->details['anciennes']['valeur'], $journal->details['nouvelles']['valeur']]);

        $this->actingAs(Utilisateur::factory()->create(['role_id' => Role::where('nom', Role::ADMINISTRATEUR)->value('id')]))
            ->put(route('parametres.update', 'apparence'), ['theme_defaut' => 'nuit', 'couleur_accent' => 'rose'])->assertSessionHasErrors(['theme_defaut', 'couleur_accent']);
    }
}
