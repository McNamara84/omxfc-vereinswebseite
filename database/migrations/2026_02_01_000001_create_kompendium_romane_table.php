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
        Schema::create('kompendium_romane', function (Blueprint $table) {
            $table->id();
            $table->string('dateiname');                    // "001 - Der Gott aus dem Eis.txt"
            $table->string('dateipfad')->unique();          // "romane/maddrax/001 - Der Gott aus dem Eis.txt"
            $table->string('serie');                        // "maddrax", "hardcovers", "missionmars", etc.
            $table->integer('roman_nr');                    // 1
            $table->string('titel');                        // "Der Gott aus dem Eis"
            $table->string('zyklus')->nullable();           // "Euree" (aus maddrax.json, falls vorhanden)
            $table->timestamp('hochgeladen_am');
            $table->foreignId('hochgeladen_von')->constrained('users');
            $table->timestamp('indexiert_am')->nullable();
            $table->string('status')->default('hochgeladen'); // hochgeladen, indexiert, indexierung_laeuft, fehler
            $table->text('fehler_nachricht')->nullable();
            $table->timestamps();

            // Kombination aus Serie + Nummer + Titel ist eindeutig
            $table->unique(['serie', 'roman_nr', 'titel'], 'kompendium_romane_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kompendium_romane');
    }
};
