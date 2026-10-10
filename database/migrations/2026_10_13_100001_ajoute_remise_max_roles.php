<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remise maximale par rôle (écran Paramètres > Ventes), en % du montant brut de la vente.
 * Vide = plafond général (paramètre « remise_max_pourcentage »). CLAUDE.md §5.9.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->decimal('remise_max', 5, 2)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('remise_max');
        });
    }
};
