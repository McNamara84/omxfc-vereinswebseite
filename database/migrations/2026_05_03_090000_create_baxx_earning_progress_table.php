<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('baxx_earning_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action_key');
            $table->unsignedInteger('processed_count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'action_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('baxx_earning_progress');
    }
};