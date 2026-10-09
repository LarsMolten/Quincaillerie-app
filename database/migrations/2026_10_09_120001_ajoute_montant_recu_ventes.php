<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Montant remis par le client et monnaie rendue à la caisse : imprimés sur le ticket,
 * y compris lors d'une réimpression. Nuls pour une vente à crédit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventes', function (Blueprint $table) {
            $table->decimal('montant_recu', 12, 2)->nullable()->after('reste_a_payer');
            $table->decimal('monnaie_rendue', 12, 2)->nullable()->after('montant_recu');
        });
    }

    public function down(): void
    {
        Schema::table('ventes', function (Blueprint $table) {
            $table->dropColumn(['montant_recu', 'monnaie_rendue']);
        });
    }
};
