<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produits', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 50)->unique();
            $table->string('code_barres', 50)->nullable()->unique();
            $table->string('nom')->index();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            // Pas de suppression en cascade : les données métier ne sont jamais effacées
            $table->foreignId('categorie_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('unite_id')->constrained('unites')->restrictOnDelete();
            $table->decimal('prix_achat', 12, 2);
            $table->decimal('prix_vente', 12, 2);
            $table->decimal('prix_gros', 12, 2)->nullable();
            // Modifié uniquement par MouvementStockService
            $table->decimal('stock_actuel', 12, 3)->default(0);
            $table->decimal('stock_minimum', 12, 3)->default(0);
            $table->boolean('actif')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produits');
    }
};
