<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('depenses', function (Blueprint $table) {
            $table->id();
            $table->enum('categorie', [
                'salaires', 'transport', 'electricite', 'eau',
                'loyer', 'entretien', 'fournitures', 'autres',
            ])->index();
            $table->string('libelle');
            $table->decimal('montant', 12, 2);
            $table->date('date_depense')->index();
            $table->enum('mode_paiement', ['especes', 'mobile_money', 'virement', 'cheque']);
            $table->foreignId('utilisateur_id')->constrained('utilisateurs')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depenses');
    }
};
