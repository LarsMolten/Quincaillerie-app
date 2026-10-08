<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            // Vente (encaissement client) ou Achat (règlement fournisseur)
            $table->morphs('payable');
            $table->decimal('montant', 12, 2);
            $table->enum('mode', ['especes', 'mobile_money', 'virement', 'cheque']);
            $table->string('reference', 100)->nullable(); // n° de chèque, de transaction Mobile Money…
            $table->dateTime('date_paiement')->index();
            $table->foreignId('utilisateur_id')->constrained('utilisateurs')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements');
    }
};
