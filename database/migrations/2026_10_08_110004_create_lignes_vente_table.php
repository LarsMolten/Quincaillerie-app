<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lignes_vente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vente_id')->constrained('ventes')->cascadeOnDelete();
            $table->foreignId('produit_id')->constrained('produits')->restrictOnDelete();
            $table->decimal('quantite', 12, 3);
            $table->decimal('prix_unitaire', 12, 2);
            // Copie du prix d'achat au moment de la vente (calcul du bénéfice)
            $table->decimal('prix_achat_unitaire', 12, 2);
            $table->decimal('remise', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lignes_vente');
    }
};
