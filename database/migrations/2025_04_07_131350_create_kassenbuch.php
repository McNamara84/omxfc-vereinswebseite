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
        Schema::create('kassenbuch_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->date('buchungsdatum');
            $table->decimal('betrag', 10, 2); // Positiv für Einnahmen, negativ für Ausgaben
            $table->string('beschreibung');
            $table->string('typ');
            $table->timestamps();
        });

        // Wir erstellen auch eine Tabelle für den aktuellen Kassenstand
        Schema::create('kassenstand', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->decimal('betrag', 10, 2)->default(0.00);
            $table->date('letzte_aktualisierung');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kassenbuch_entries');
        Schema::dropIfExists('kassenstand');
    }
};
