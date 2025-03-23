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
        Schema::table('users', function (Blueprint $table) {
            $table->string('vorname');
            $table->string('nachname');
            $table->string('strasse');
            $table->string('hausnummer');
            $table->string('plz');
            $table->string('stadt');
            $table->string('land');
            $table->string('telefon')->nullable();
            $table->string('verein_gefunden')->nullable();
            $table->decimal('mitgliedsbeitrag', 6, 2)->default(12.00);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'vorname',
                'nachname',
                'strasse',
                'hausnummer',
                'plz',
                'stadt',
                'land',
                'telefon',
                'verein_gefunden',
                'mitgliedsbeitrag'
            ]);
        });
    }
};
