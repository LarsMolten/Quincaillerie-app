<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Numéro de reçu des paiements : REC-AAAA-NNNNN, sans trou, remis à zéro chaque année (CLAUDE.md §5.6).
 * Les paiements existants sont numérotés par année de date_paiement, dans l'ordre chronologique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->string('numero', 20)->nullable()->after('id');
        });

        $compteurs = [];
        DB::table('paiements')->orderBy('date_paiement')->orderBy('id')->select(['id', 'date_paiement'])
            ->each(function (object $paiement) use (&$compteurs) {
                $annee = Carbon::parse($paiement->date_paiement)->year;
                $compteurs[$annee] = ($compteurs[$annee] ?? 0) + 1;

                DB::table('paiements')->where('id', $paiement->id)->update([
                    'numero' => sprintf('REC-%d-%05d', $annee, $compteurs[$annee]),
                ]);
            });

        Schema::table('paiements', function (Blueprint $table) {
            // SQLite (tests) reconstruit la table pour change() et perdrait la contrainte CHECK de « mode » :
            // la colonne y reste nullable, PaiementService attribuant toujours le numéro
            if (DB::getDriverName() !== 'sqlite') {
                $table->string('numero', 20)->nullable(false)->change();
            }
            $table->unique('numero');
        });
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->dropUnique(['numero']);
            $table->dropColumn('numero');
        });
    }
};
