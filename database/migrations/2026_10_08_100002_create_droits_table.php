<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('droits', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // ex. « ventes.creer »
            $table->string('libelle');
            $table->string('module')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('droits');
    }
};
