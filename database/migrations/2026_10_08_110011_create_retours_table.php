<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retours', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique();
            $table->enum('type', ['client', 'fournisseur']);
            // Retour client => vente_id ; retour fournisseur => achat_id
            $table->foreignId('vente_id')->nullable()->constrained('ventes')->restrictOnDelete();
            $table->foreignId('achat_id')->nullable()->constrained('achats')->restrictOnDelete();
            $table->foreignId('utilisateur_id')->constrained('utilisateurs')->restrictOnDelete();
            $table->date('date_retour')->index();
            $table->decimal('total', 12, 2);
            $table->string('motif');
            $table->enum('statut', ['valide', 'annule'])->default('valide');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retours');
    }
};
