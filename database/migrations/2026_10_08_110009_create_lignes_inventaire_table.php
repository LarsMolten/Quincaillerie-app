<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lignes_inventaire', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventaire_id')->constrained('inventaires')->cascadeOnDelete();
            $table->foreignId('produit_id')->constrained('produits')->restrictOnDelete();
            $table->decimal('stock_theorique', 12, 3);
            $table->decimal('stock_compte', 12, 3)->nullable(); // null tant que le produit n'est pas compté
            $table->decimal('ecart', 12, 3)->nullable();

            // Un produit n'est compté qu'une fois par inventaire
            $table->unique(['inventaire_id', 'produit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lignes_inventaire');
    }
};
