<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_baxx_special_offers', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('points');
            $table->unsignedInteger('every_count')->default(1);
            $table->timestamp('ends_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_baxx_special_offers');
    }
};