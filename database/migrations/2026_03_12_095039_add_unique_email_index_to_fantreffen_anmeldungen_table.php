<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Duplikate bereinigen bevor der Unique-Index angelegt wird:
        // Pro E-Mail wird nur die älteste Anmeldung (kleinste ID) behalten.
        // Set-basierte Löschung in einer Transaktion für Performance und Konsistenz.
        DB::transaction(function () {
            DB::table('fantreffen_anmeldungen')
                ->whereNotNull('email')
                ->whereNotIn('id', function ($query) {
                    $query->select(DB::raw('MIN(id)'))
                        ->from('fantreffen_anmeldungen')
                        ->whereNotNull('email')
                        ->groupBy('email');
                })
                ->delete();
        });

        Schema::table('fantreffen_anmeldungen', function (Blueprint $table) {
            $table->unique('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fantreffen_anmeldungen', function (Blueprint $table) {
            $table->dropUnique(['email']);
        });
    }
};
