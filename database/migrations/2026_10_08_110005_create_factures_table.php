<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('factures', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique(); // FAC-AAAA-NNNNN
            $table->foreignId('vente_id')->unique()->constrained('ventes')->restrictOnDelete();
            $table->dateTime('date_emission')->index();
            $table->decimal('total', 12, 2);
            $table->enum('statut', ['emise', 'annulee'])->default('emise');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factures');
    }
};
