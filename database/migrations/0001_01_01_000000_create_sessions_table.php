<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tables techniques de l'authentification.
 * La table des comptes est « utilisateurs » (migration dédiée, après « roles »).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reinitialisations_mot_de_passe', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // La colonne « user_id » est imposée par le gestionnaire de sessions de Laravel ;
        // elle contient l'identifiant de l'utilisateur connecté (table « utilisateurs »).
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('reinitialisations_mot_de_passe');
    }
};
