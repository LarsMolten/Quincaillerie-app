<?php

namespace Tests\Feature;

use App\Models\Utilisateur;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationsReferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_tables_de_reference_ont_les_colonnes_attendues(): void
    {
        $colonnesAttendues = [
            'roles' => ['id', 'nom', 'description', 'created_at', 'updated_at'],
            'droits' => ['id', 'code', 'libelle', 'module', 'created_at', 'updated_at'],
            'role_droit' => ['role_id', 'droit_id'],
            'utilisateurs' => [
                'id', 'nom', 'email', 'telephone', 'password', 'role_id', 'actif',
                'preference_theme', 'remember_token', 'created_at', 'updated_at', 'deleted_at',
            ],
            'categories' => ['id', 'nom', 'description', 'actif', 'created_at', 'updated_at'],
            'unites' => ['id', 'nom', 'abreviation', 'created_at', 'updated_at'],
            'produits' => [
                'id', 'reference', 'code_barres', 'nom', 'description', 'image', 'categorie_id',
                'unite_id', 'prix_achat', 'prix_vente', 'prix_gros', 'stock_actuel', 'stock_minimum',
                'actif', 'created_at', 'updated_at', 'deleted_at',
            ],
            'fournisseurs' => [
                'id', 'nom', 'contact', 'telephone', 'email', 'adresse', 'actif',
                'created_at', 'updated_at', 'deleted_at',
            ],
            'clients' => [
                'id', 'nom', 'telephone', 'email', 'adresse', 'plafond_credit', 'actif',
                'created_at', 'updated_at', 'deleted_at',
            ],
            'parametres' => ['id', 'cle', 'valeur', 'created_at', 'updated_at'],
        ];

        foreach ($colonnesAttendues as $table => $colonnes) {
            $this->assertTrue(Schema::hasTable($table), "La table « {$table} » est absente.");
            $this->assertTrue(Schema::hasColumns($table, $colonnes), "Colonnes manquantes dans « {$table} ».");
        }

        $this->assertFalse(Schema::hasTable('users'));
        $this->assertFalse(Schema::hasTable('password_reset_tokens'));
    }

    public function test_les_valeurs_par_defaut_sont_appliquees(): void
    {
        $roleId = DB::table('roles')->insertGetId(['nom' => 'Vendeur']);
        $utilisateurId = DB::table('utilisateurs')->insertGetId([
            'nom' => 'Rakoto',
            'email' => 'rakoto@quincaillerie.test',
            'password' => 'secret',
            'role_id' => $roleId,
        ]);

        $utilisateur = DB::table('utilisateurs')->find($utilisateurId);
        $this->assertSame('auto', $utilisateur->preference_theme);
        $this->assertEquals(1, $utilisateur->actif);

        $produitId = DB::table('produits')->insertGetId([
            'reference' => 'CIM-001',
            'nom' => 'Ciment 50 kg',
            'categorie_id' => DB::table('categories')->insertGetId(['nom' => 'Maçonnerie']),
            'unite_id' => DB::table('unites')->insertGetId(['nom' => 'sac', 'abreviation' => 'sac']),
            'prix_achat' => 32000,
            'prix_vente' => 35000,
        ]);

        $produit = DB::table('produits')->find($produitId);
        $this->assertEquals(0, $produit->stock_actuel);
        $this->assertEquals(0, $produit->stock_minimum);
        $this->assertEquals(1, $produit->actif);
    }

    public function test_la_reference_produit_est_unique(): void
    {
        $categorieId = DB::table('categories')->insertGetId(['nom' => 'Plomberie']);
        $uniteId = DB::table('unites')->insertGetId(['nom' => 'pièce', 'abreviation' => 'pce']);
        $produit = [
            'reference' => 'PLB-001',
            'nom' => 'Robinet',
            'categorie_id' => $categorieId,
            'unite_id' => $uniteId,
            'prix_achat' => 10000,
            'prix_vente' => 15000,
        ];

        DB::table('produits')->insert($produit);

        $this->expectException(QueryException::class);
        DB::table('produits')->insert($produit);
    }

    public function test_supprimer_un_role_supprime_ses_liaisons_de_droits(): void
    {
        $roleId = DB::table('roles')->insertGetId(['nom' => 'Magasinier']);
        $droitId = DB::table('droits')->insertGetId([
            'code' => 'stock.ajuster',
            'libelle' => 'Ajuster le stock',
            'module' => 'stock',
        ]);
        DB::table('role_droit')->insert(['role_id' => $roleId, 'droit_id' => $droitId]);

        DB::table('roles')->where('id', $roleId)->delete();

        $this->assertSame(0, DB::table('role_droit')->count());
        $this->assertSame(1, DB::table('droits')->count());
    }

    public function test_un_role_utilise_par_un_utilisateur_ne_peut_pas_etre_supprime(): void
    {
        $roleId = DB::table('roles')->insertGetId(['nom' => 'Responsable']);
        DB::table('utilisateurs')->insert([
            'nom' => 'Rasoa',
            'email' => 'rasoa@quincaillerie.test',
            'password' => 'secret',
            'role_id' => $roleId,
        ]);

        $this->expectException(QueryException::class);
        DB::table('roles')->where('id', $roleId)->delete();
    }

    public function test_l_authentification_utilise_le_modele_utilisateur(): void
    {
        $this->assertSame(Utilisateur::class, config('auth.providers.utilisateurs.model'));
        $this->assertSame('reinitialisations_mot_de_passe', config('auth.passwords.utilisateurs.table'));
        $this->assertSame('utilisateurs', (new Utilisateur)->getTable());
    }
}
