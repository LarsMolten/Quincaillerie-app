<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->index();
            $table->string('telephone', 30)->nullable()->index();
            $table->string('email')->nullable();
            $table->string('adresse')->nullable();
            // Null = plafond non défini (règle appliquée par le service de vente)
            $table->decimal('plafond_credit', 12, 2)->nullable();
            $table->boolean('actif')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
