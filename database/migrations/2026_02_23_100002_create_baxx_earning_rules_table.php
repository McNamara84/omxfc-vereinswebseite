<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('baxx_earning_rules', function (Blueprint $table) {
            $table->id();
            $table->string('action_key')->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->unsignedInteger('points');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('baxx_earning_rules');
    }
};
