<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mouvements_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produit_id')->constrained('produits')->restrictOnDelete();
            $table->enum('type', [
                'achat', 'vente', 'retour_client', 'retour_fournisseur',
                'ajustement_positif', 'ajustement_negatif', 'perte',
            ]);
            $table->enum('sens', ['entree', 'sortie']);
            $table->decimal('quantite', 12, 3);
            $table->decimal('stock_avant', 12, 3);
            $table->decimal('stock_apres', 12, 3);
            $table->foreignId('utilisateur_id')->constrained('utilisateurs')->restrictOnDelete();
            $table->string('motif')->nullable();
            // Document à l'origine du mouvement (achat, vente, retour, inventaire)
            $table->nullableMorphs('reference');
            // Historique non modifiable : pas de updated_at
            $table->timestamp('created_at')->useCurrent();

            $table->index(['produit_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mouvements_stock');
    }
};
