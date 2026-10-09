<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suppression douce des catégories et des unités (CLAUDE.md §5.5) :
 * seules celles qui ne sont utilisées par aucun produit peuvent être « supprimées ».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('unites', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('unites', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
