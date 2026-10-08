<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventes', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique(); // VTE-AAAA-NNNNN
            $table->foreignId('client_id')->nullable()->constrained('clients')->restrictOnDelete();
            $table->foreignId('utilisateur_id')->constrained('utilisateurs')->restrictOnDelete();
            $table->dateTime('date_vente')->index();
            $table->decimal('sous_total', 12, 2);
            $table->decimal('remise', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->decimal('montant_paye', 12, 2)->default(0);
            $table->decimal('reste_a_payer', 12, 2)->default(0);
            $table->enum('mode_paiement', ['especes', 'mobile_money', 'virement', 'cheque', 'credit']);
            $table->enum('statut', ['validee', 'annulee'])->default('validee');
            $table->text('notes')->nullable();
            $table->timestamps();

            // Rapports par période sur les ventes validées
            $table->index(['statut', 'date_vente']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventes');
    }
};
