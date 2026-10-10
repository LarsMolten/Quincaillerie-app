<?php

namespace Tests\Feature\Paiements;

use App\Models\Utilisateur;
use App\Models\Vente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Migration « ajoute_numero_paiements » : les paiements déjà enregistrés reçoivent un numéro de reçu
 * par année de date_paiement, dans l'ordre chronologique.
 */
class MigrationNumeroRecuTest extends TestCase
{
    use RefreshDatabase;

    public function test_numerote_les_paiements_existants_par_annee(): void
    {
        $this->artisan('migrate:rollback', ['--path' => 'database/migrations/2026_10_10_120001_ajoute_numero_paiements.php'])->assertSuccessful();
        $this->assertFalse(Schema::hasColumn('paiements', 'numero'));

        $vente = Vente::factory()->create();
        $utilisateur = Utilisateur::factory()->create();
        $inserer = fn (string $date) => DB::table('paiements')->insertGetId([
            'payable_type' => 'vente', 'payable_id' => $vente->id, 'montant' => 1000, 'mode' => 'especes',
            'date_paiement' => $date, 'utilisateur_id' => $utilisateur->id,
        ]);
        // Insérés dans le désordre : la numérotation suit la date
        $mars2026 = $inserer('2026-03-01 10:00:00');
        $decembre2025 = $inserer('2025-12-31 18:00:00');
        $janvier2026 = $inserer('2026-01-05 08:00:00');

        $this->artisan('migrate', ['--path' => 'database/migrations/2026_10_10_120001_ajoute_numero_paiements.php'])->assertSuccessful();

        $this->assertSame('REC-2025-00001', DB::table('paiements')->where('id', $decembre2025)->value('numero'));
        $this->assertSame('REC-2026-00001', DB::table('paiements')->where('id', $janvier2026)->value('numero'));
        $this->assertSame('REC-2026-00002', DB::table('paiements')->where('id', $mars2026)->value('numero'));
    }
}
