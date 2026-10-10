<?php

namespace Tests\Feature;

use App\Enums\CategorieDepense;
use App\Enums\ModePaiement;
use App\Enums\SensMouvement;
use App\Enums\StatutAchat;
use App\Enums\StatutFacture;
use App\Enums\StatutInventaire;
use App\Enums\StatutRetour;
use App\Enums\StatutVente;
use App\Enums\TypeMouvementStock;
use App\Enums\TypeRetour;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationsTransactionsTest extends TestCase
{
    use RefreshDatabase;

    private int $utilisateurId;

    private int $produitId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->utilisateurId = DB::table('utilisateurs')->insertGetId([
            'nom' => 'Rakoto',
            'email' => 'rakoto@quincaillerie.test',
            'password' => 'secret',
            'role_id' => DB::table('roles')->insertGetId(['nom' => 'Vendeur']),
        ]);

        $this->produitId = DB::table('produits')->insertGetId([
            'reference' => 'CIM-001',
            'nom' => 'Ciment 50 kg',
            'categorie_id' => DB::table('categories')->insertGetId(['nom' => 'Maçonnerie']),
            'unite_id' => DB::table('unites')->insertGetId(['nom' => 'sac', 'abreviation' => 'sac']),
            'prix_achat' => 32000,
            'prix_vente' => 35000,
        ]);
    }

    public function test_les_tables_de_transactions_ont_les_colonnes_attendues(): void
    {
        $colonnesAttendues = [
            'achats' => [
                'id', 'numero', 'fournisseur_id', 'utilisateur_id', 'date_achat', 'total',
                'montant_paye', 'reste_a_payer', 'statut', 'notes', 'created_at', 'updated_at',
            ],
            'lignes_achat' => ['id', 'achat_id', 'produit_id', 'quantite', 'prix_achat', 'total'],
            'ventes' => [
                'id', 'numero', 'client_id', 'utilisateur_id', 'date_vente', 'sous_total', 'remise',
                'total', 'montant_paye', 'reste_a_payer', 'mode_paiement', 'statut', 'notes',
                'created_at', 'updated_at',
            ],
            'lignes_vente' => [
                'id', 'vente_id', 'produit_id', 'quantite', 'prix_unitaire',
                'prix_achat_unitaire', 'remise', 'total',
            ],
            'factures' => ['id', 'numero', 'vente_id', 'date_emission', 'total', 'statut', 'created_at', 'updated_at'],
            'paiements' => [
                'id', 'payable_type', 'payable_id', 'montant', 'mode', 'reference',
                'date_paiement', 'utilisateur_id', 'created_at', 'updated_at',
            ],
            'mouvements_stock' => [
                'id', 'produit_id', 'type', 'sens', 'quantite', 'stock_avant', 'stock_apres',
                'utilisateur_id', 'motif', 'reference_type', 'reference_id', 'created_at',
            ],
            'inventaires' => [
                'id', 'numero', 'date_inventaire', 'statut', 'utilisateur_id', 'notes', 'created_at', 'updated_at',
            ],
            'lignes_inventaire' => ['id', 'inventaire_id', 'produit_id', 'stock_theorique', 'stock_compte', 'ecart'],
            'depenses' => [
                'id', 'categorie', 'libelle', 'montant', 'date_depense', 'mode_paiement',
                'utilisateur_id', 'notes', 'created_at', 'updated_at',
            ],
            'retours' => [
                'id', 'numero', 'type', 'vente_id', 'achat_id', 'utilisateur_id', 'date_retour',
                'total', 'motif', 'statut', 'created_at', 'updated_at',
            ],
            'lignes_retour' => ['id', 'retour_id', 'produit_id', 'quantite', 'prix_unitaire', 'total'],
            'journal_activites' => [
                'id', 'utilisateur_id', 'action', 'modele', 'modele_id', 'details', 'adresse_ip', 'created_at',
            ],
        ];

        foreach ($colonnesAttendues as $table => $colonnes) {
            $this->assertTrue(Schema::hasTable($table), "La table « {$table} » est absente.");
            $this->assertTrue(Schema::hasColumns($table, $colonnes), "Colonnes manquantes dans « {$table} ».");
        }

        // Historiques non modifiables
        $this->assertFalse(Schema::hasColumn('mouvements_stock', 'updated_at'));
        $this->assertFalse(Schema::hasColumn('journal_activites', 'updated_at'));
    }

    public function test_les_colonnes_acceptent_toutes_les_valeurs_des_enums(): void
    {
        $achatId = $this->creerAchat();
        foreach (StatutAchat::cases() as $statut) {
            DB::table('achats')->where('id', $achatId)->update(['statut' => $statut->value]);
        }

        foreach (ModePaiement::cases() as $i => $mode) {
            $venteId = $this->creerVente("VTE-2026-0000{$i}", ['mode_paiement' => $mode->value]);
        }
        foreach (StatutVente::cases() as $statut) {
            DB::table('ventes')->where('id', $venteId)->update(['statut' => $statut->value]);
        }

        $factureId = DB::table('factures')->insertGetId([
            'numero' => 'FAC-2026-00001',
            'vente_id' => $venteId,
            'date_emission' => now(),
            'total' => 35000,
        ]);
        foreach (StatutFacture::cases() as $statut) {
            DB::table('factures')->where('id', $factureId)->update(['statut' => $statut->value]);
        }

        foreach (ModePaiement::encaissements() as $mode) {
            DB::table('paiements')->insert([
                'numero' => 'REC-2026-'.$mode->value,
                'payable_type' => 'vente',
                'payable_id' => $venteId,
                'montant' => 1000,
                'mode' => $mode->value,
                'date_paiement' => now(),
                'utilisateur_id' => $this->utilisateurId,
            ]);
            DB::table('depenses')->insert([
                'categorie' => CategorieDepense::Autres->value,
                'libelle' => 'Essai',
                'montant' => 1000,
                'date_depense' => today(),
                'mode_paiement' => $mode->value,
                'utilisateur_id' => $this->utilisateurId,
            ]);
        }

        foreach (CategorieDepense::cases() as $categorie) {
            DB::table('depenses')->insert([
                'categorie' => $categorie->value,
                'libelle' => 'Essai',
                'montant' => 1000,
                'date_depense' => today(),
                'mode_paiement' => ModePaiement::Especes->value,
                'utilisateur_id' => $this->utilisateurId,
            ]);
        }

        foreach (TypeMouvementStock::cases() as $type) {
            DB::table('mouvements_stock')->insert([
                'produit_id' => $this->produitId,
                'type' => $type->value,
                'sens' => $type->sens()->value,
                'quantite' => 1,
                'stock_avant' => 10,
                'stock_apres' => $type->sens() === SensMouvement::Entree ? 11 : 9,
                'utilisateur_id' => $this->utilisateurId,
            ]);
        }

        $inventaireId = DB::table('inventaires')->insertGetId([
            'numero' => 'INV-2026-00001',
            'date_inventaire' => today(),
            'utilisateur_id' => $this->utilisateurId,
        ]);
        foreach (StatutInventaire::cases() as $statut) {
            DB::table('inventaires')->where('id', $inventaireId)->update(['statut' => $statut->value]);
        }

        foreach (TypeRetour::cases() as $i => $type) {
            $retourId = DB::table('retours')->insertGetId([
                'numero' => "RET-2026-0000{$i}",
                'type' => $type->value,
                'utilisateur_id' => $this->utilisateurId,
                'date_retour' => today(),
                'total' => 0,
                'motif' => 'Produit défectueux',
            ]);
        }
        foreach (StatutRetour::cases() as $statut) {
            DB::table('retours')->where('id', $retourId)->update(['statut' => $statut->value]);
        }

        $this->assertSame(count(TypeMouvementStock::cases()), DB::table('mouvements_stock')->count());
    }

    public function test_une_valeur_hors_enum_est_refusee(): void
    {
        $this->expectException(QueryException::class);
        $this->creerVente('VTE-2026-00001', ['mode_paiement' => 'bitcoin']);
    }

    public function test_les_paiements_refusent_le_mode_credit(): void
    {
        $this->expectException(QueryException::class);
        DB::table('paiements')->insert([
            'numero' => 'REC-2026-00001',
            'payable_type' => 'vente',
            'payable_id' => $this->creerVente('VTE-2026-00001'),
            'montant' => 1000,
            'mode' => ModePaiement::Credit->value,
            'date_paiement' => now(),
            'utilisateur_id' => $this->utilisateurId,
        ]);
    }

    public function test_le_numero_de_vente_est_unique(): void
    {
        $this->creerVente('VTE-2026-00001');

        $this->expectException(QueryException::class);
        $this->creerVente('VTE-2026-00001');
    }

    public function test_une_vente_n_a_qu_une_facture(): void
    {
        $venteId = $this->creerVente('VTE-2026-00001');
        $facture = ['vente_id' => $venteId, 'date_emission' => now(), 'total' => 35000];
        DB::table('factures')->insert(['numero' => 'FAC-2026-00001', ...$facture]);

        $this->expectException(QueryException::class);
        DB::table('factures')->insert(['numero' => 'FAC-2026-00002', ...$facture]);
    }

    public function test_supprimer_une_vente_supprime_ses_lignes(): void
    {
        $venteId = $this->creerVente('VTE-2026-00001');
        DB::table('lignes_vente')->insert([
            'vente_id' => $venteId,
            'produit_id' => $this->produitId,
            'quantite' => 1,
            'prix_unitaire' => 35000,
            'prix_achat_unitaire' => 32000,
            'total' => 35000,
        ]);

        DB::table('ventes')->where('id', $venteId)->delete();

        $this->assertSame(0, DB::table('lignes_vente')->count());
    }

    public function test_un_produit_vendu_ne_peut_pas_etre_supprime_definitivement(): void
    {
        DB::table('lignes_vente')->insert([
            'vente_id' => $this->creerVente('VTE-2026-00001'),
            'produit_id' => $this->produitId,
            'quantite' => 1,
            'prix_unitaire' => 35000,
            'prix_achat_unitaire' => 32000,
            'total' => 35000,
        ]);

        $this->expectException(QueryException::class);
        DB::table('produits')->where('id', $this->produitId)->delete();
    }

    public function test_un_produit_n_est_compte_qu_une_fois_par_inventaire(): void
    {
        $inventaireId = DB::table('inventaires')->insertGetId([
            'numero' => 'INV-2026-00001',
            'date_inventaire' => today(),
            'utilisateur_id' => $this->utilisateurId,
        ]);
        $ligne = ['inventaire_id' => $inventaireId, 'produit_id' => $this->produitId, 'stock_theorique' => 10];
        DB::table('lignes_inventaire')->insert($ligne);

        $this->expectException(QueryException::class);
        DB::table('lignes_inventaire')->insert($ligne);
    }

    private function creerAchat(): int
    {
        return DB::table('achats')->insertGetId([
            'numero' => 'ACH-2026-00001',
            'fournisseur_id' => DB::table('fournisseurs')->insertGetId(['nom' => 'Holcim', 'telephone' => '0340000000']),
            'utilisateur_id' => $this->utilisateurId,
            'date_achat' => today(),
            'total' => 320000,
            'reste_a_payer' => 320000,
        ]);
    }

    private function creerVente(string $numero, array $valeurs = []): int
    {
        return DB::table('ventes')->insertGetId([
            'numero' => $numero,
            'utilisateur_id' => $this->utilisateurId,
            'date_vente' => now(),
            'sous_total' => 35000,
            'total' => 35000,
            'mode_paiement' => ModePaiement::Especes->value,
            ...$valeurs,
        ]);
    }
}
