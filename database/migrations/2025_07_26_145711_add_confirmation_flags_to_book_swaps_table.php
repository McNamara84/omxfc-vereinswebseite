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
        Schema::table('book_swaps', function (Blueprint $table) {
            $table->boolean('offer_confirmed')->default(false);
            $table->boolean('request_confirmed')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('book_swaps', function (Blueprint $table) {
            $table->dropColumn(['offer_confirmed', 'request_confirmed']);
        });
    }
};
