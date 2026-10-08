<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_droit', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('droit_id')->constrained('droits')->cascadeOnDelete();

            $table->primary(['role_id', 'droit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_droit');
    }
};
