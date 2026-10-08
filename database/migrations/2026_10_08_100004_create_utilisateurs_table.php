<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utilisateurs', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('email')->unique();
            $table->string('telephone', 30)->nullable();
            $table->string('password');
            // Un rôle utilisé par des comptes ne peut pas être supprimé
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->boolean('actif')->default(true)->index();
            $table->enum('preference_theme', ['clair', 'sombre', 'auto'])->default('auto');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utilisateurs');
    }
};
