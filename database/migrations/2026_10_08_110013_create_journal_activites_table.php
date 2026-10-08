<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_activites', function (Blueprint $table) {
            $table->id();
            // Null = action système
            $table->foreignId('utilisateur_id')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->string('action', 100)->index(); // ex. « vente.annulee », « connexion »
            $table->string('modele')->nullable();
            $table->unsignedBigInteger('modele_id')->nullable();
            $table->json('details')->nullable();
            $table->string('adresse_ip', 45)->nullable();
            // Journal non modifiable : pas de updated_at
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['modele', 'modele_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_activites');
    }
};
