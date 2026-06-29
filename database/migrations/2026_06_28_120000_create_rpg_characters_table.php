<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rpg_characters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('character_name');
            $table->json('payload');
            $table->string('portrait_path')->nullable();
            $table->string('portrait_mime', 100)->nullable();
            $table->string('portrait_original_name')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'character_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rpg_characters');
    }
};
