<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unites', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->unique(); // ex. « kilogramme »
            $table->string('abreviation', 20)->unique(); // ex. « kg »
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unites');
    }
};
