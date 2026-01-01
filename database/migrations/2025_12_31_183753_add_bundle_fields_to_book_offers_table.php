<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * HINWEIS zu bundle_id ohne Foreign Key:
     * bundle_id ist eine UUID ohne Foreign-Key-Constraint, da es keine separate
     * bundles-Tabelle gibt. Der erste Eintrag im Bundle definiert die Bundle-ID,
     * alle weiteren Angebote im Stapel teilen dieselbe UUID.
     *
     * Konsequenzen:
     * - Kein referentielles Constraint, da kein Master-Record existiert
     * - Verwaiste bundle_ids sind möglich wenn alle Angebote eines Bundles gelöscht werden
     *   (keine Auswirkung auf Funktionalität, IDs werden nicht wiederverwendet)
     * - Activity-Log Einträge mit action='bundle_created' können auf gelöschte Angebote
     *   verweisen (bekannte Limitation, siehe RomantauschController::storeBundleOffer)
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
