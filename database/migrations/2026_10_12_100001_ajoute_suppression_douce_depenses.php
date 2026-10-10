<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suppression douce des dépenses (CLAUDE.md §5.5) : une dépense supprimée par l'Administrateur
 * disparaît des listes et des totaux mais reste en base pour l'audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('depenses', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('depenses', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
