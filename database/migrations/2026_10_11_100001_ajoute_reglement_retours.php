<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Règlement d'un retour : la part imputée sur le reste à payer du document (avoir sur créance
 * ou diminution de la dette fournisseur) et l'excédent remboursé, avec son mode.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('retours', function (Blueprint $table) {
            $table->decimal('montant_avoir', 12, 2)->default(0)->after('total');
            $table->decimal('montant_rembourse', 12, 2)->default(0)->after('montant_avoir');
            $table->enum('mode_remboursement', ['especes', 'mobile_money', 'virement', 'cheque'])->nullable()->after('montant_rembourse');
        });
    }

    public function down(): void
    {
        Schema::table('retours', function (Blueprint $table) {
            $table->dropColumn(['montant_avoir', 'montant_rembourse', 'mode_remboursement']);
        });
    }
};
