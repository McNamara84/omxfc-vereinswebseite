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
        Schema::table('book_offers', function (Blueprint $table) {
            // Bundle-Zugehörigkeit (NULL = Einzelangebot)
            $table->uuid('bundle_id')->nullable()->after('id');

            // Zustandsbereich für Stapel (condition bleibt für Einzelangebote/Min-Wert)
            $table->string('condition_max')->nullable()->after('condition');

            // Index für schnelle Bundle-Abfragen
            $table->index('bundle_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('book_offers', function (Blueprint $table) {
            $table->dropIndex(['bundle_id']);
            $table->dropColumn(['bundle_id', 'condition_max']);
        });
    }
};
