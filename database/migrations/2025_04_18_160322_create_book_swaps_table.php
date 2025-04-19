<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('book_swaps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained('book_offers')->cascadeOnDelete();
            $table->foreignId('request_id')->constrained('book_requests')->cascadeOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('book_swaps');
    }
};
