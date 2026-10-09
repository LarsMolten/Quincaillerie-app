<?php

namespace Tests\Feature\Produits;

use App\Enums\SensMouvement;
use App\Enums\TypeMouvementStock;
use App\Models\Categorie;
use App\Models\MouvementStock;
use App\Models\Produit;
use App\Support\Ean13;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CreationProduitTest extends ProduitsTestCase
{
    public function test_reference_automatique_et_sequentielle(): void
    {
        $this->post(route('produits.store'), $this->donnees())
            ->assertRedirect(route('produits.index'))
            ->assertSessionHas('succes', 'Produit « Ciment CEM II 42,5 – sac 50 kg » créé (PRD-00001).');

        $this->post(route('produits.store'), $this->donnees(['nom' => 'Chaux vive']));

        $this->assertSame(['PRD-00001', 'PRD-00002'], Produit::orderBy('id')->pluck('reference')->all());
    }

    public function test_reference_saisie_conservee(): void
    {
        $this->post(route('produits.store'), $this->donnees(['reference' => 'CIM-42']));

        $this->assertDatabaseHas('produits', ['reference' => 'CIM-42']);
    }

    public function test_le_stock_initial_passe_par_un_mouvement_de_stock(): void
    {
        $this->post(route('produits.store'), $this->donnees(['stock_initial' => '120']));

        $produit = Produit::firstOrFail();
        $mouvement = MouvementStock::where('produit_id', $produit->id)->sole();

        $this->assertEquals(120, $produit->stock_actuel);
        $this->assertSame(TypeMouvementStock::AjustementPositif, $mouvement->type);
        $this->assertSame(SensMouvement::Entree, $mouvement->sens);
        $this->assertSame('Stock initial', $mouvement->motif);
        $this->assertEquals(0, $mouvement->stock_avant);
        $this->assertEquals(120, $mouvement->stock_apres);
        $this->assertTrue($mouvement->reference->is($produit));
        $this->assertSame(auth()->id(), $mouvement->utilisateur_id);
    }

    public function test_sans_stock_initial_aucun_mouvement(): void
    {
        $this->post(route('produits.store'), $this->donnees());

        $this->assertEquals(0, Produit::firstOrFail()->stock_actuel);
        $this->assertSame(0, MouvementStock::count());
    }

    public function test_le_stock_actuel_ne_peut_pas_etre_force(): void
    {
        $this->post(route('produits.store'), $this->donnees(['stock_actuel' => '999']));

        $this->assertEquals(0, Produit::firstOrFail()->stock_actuel);
    }

    public function test_unicite_reference_et_code_barres(): void
    {
        Produit::factory()->create(['reference' => 'CIM-42', 'code_barres' => '2000000000015']);

        $this->post(route('produits.store'), $this->donnees(['reference' => 'CIM-42', 'code_barres' => '2000000000015']))
            ->assertSessionHasErrors([
                'reference' => 'Cette référence est déjà utilisée par un autre produit.',
                'code_barres' => 'Ce code-barres est déjà attribué à un autre produit.',
            ])
            ->assertSessionHasInput('_formulaire', 'nouveau');
    }

    public function test_ean13_invalide_refuse_et_ean13_genere_valide(): void
    {
        $this->post(route('produits.store'), $this->donnees(['code_barres' => '2000000000016']))
            ->assertSessionHasErrors('code_barres');

        $code = $this->getJson(route('produits.code-barres'))->assertOk()->json('code');
        $this->assertTrue(Ean13::estValide($code));
        $this->assertStringStartsWith('200', $code);

        $this->post(route('produits.store'), $this->donnees(['code_barres' => $code]))->assertSessionHasNoErrors();
        $this->assertNotSame($code, $this->getJson(route('produits.code-barres'))->json('code'));
    }

    public function test_champs_obligatoires_en_francais(): void
    {
        $this->post(route('produits.store'), ['_formulaire' => 'nouveau'])
            ->assertSessionHasErrors([
                'nom' => 'Le champ nom est obligatoire.',
                'categorie_id' => 'Le champ catégorie est obligatoire.',
                'prix_vente' => 'Le champ prix de vente est obligatoire.',
            ]);
    }

    public function test_prix_de_vente_inferieur_au_prix_d_achat_avertit_sans_bloquer(): void
    {
        $this->post(route('produits.store'), $this->donnees(['prix_achat' => '40000', 'prix_vente' => '37000']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('succes')
            ->assertSessionHas('alerte', 'Attention : le prix de vente est inférieur au prix d\'achat (marge négative).');

        $this->assertSame(1, Produit::count());
    }

    public function test_montants_saisis_avec_espaces(): void
    {
        $this->post(route('produits.store'), $this->donnees(['prix_achat' => '33 000', 'prix_vente' => '37 000']))
            ->assertSessionHasNoErrors();

        $this->assertEquals(37000, Produit::firstOrFail()->prix_vente);
    }

    public function test_categorie_inactive_refusee(): void
    {
        $inactive = Categorie::factory()->inactif()->create();

        $this->post(route('produits.store'), $this->donnees(['categorie_id' => $inactive->id]))
            ->assertSessionHasErrors(['categorie_id' => 'Choisissez une catégorie active.']);
    }

    public function test_photo_redimensionnee_en_webp_et_servie(): void
    {
        Storage::fake('local');

        $this->post(route('produits.store'), $this->donnees([
            'photo' => UploadedFile::fake()->image('ciment.jpg', 1600, 1200),
        ]))->assertSessionHasNoErrors();

        $produit = Produit::firstOrFail();
        $this->assertStringStartsWith('produits/', $produit->image);
        $this->assertStringEndsWith('.webp', $produit->image);
        Storage::disk('local')->assertExists($produit->image);

        [$largeur, $hauteur] = getimagesizefromstring(Storage::disk('local')->get($produit->image));
        $this->assertSame([800, 600], [$largeur, $hauteur]);

        $this->get(route('produits.photo', $produit))->assertOk()->assertHeader('content-type', 'image/webp');
    }

    public function test_fichier_non_image_refuse(): void
    {
        $this->post(route('produits.store'), $this->donnees([
            'photo' => UploadedFile::fake()->create('facture.pdf', 100, 'application/pdf'),
        ]))->assertSessionHasErrors('photo');
    }
}
