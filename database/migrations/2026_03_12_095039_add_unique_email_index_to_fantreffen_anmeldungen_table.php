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
        // Vor dem Anlegen der Unique-Indices prüfen, ob Duplikate existieren.
        // Bei Duplikaten wird die Migration abgebrochen – manuelles Cleanup erforderlich,
        // da automatisches Löschen die falsche Anmeldung entfernen könnte.
        $emailDuplicates = DB::table('fantreffen_anmeldungen')
            ->select('email', DB::raw('COUNT(*) as count'))
            ->whereNotNull('email')
            ->groupBy('email')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($emailDuplicates->isNotEmpty()) {
            $list = $emailDuplicates->pluck('email')->implode(', ');
            throw new RuntimeException(
                "Migration abgebrochen: Es existieren doppelte E-Mail-Adressen in fantreffen_anmeldungen ({$list}). "
                .'Bitte manuell bereinigen, bevor der Unique-Index angelegt werden kann.'
            );
        }

        $userIdDuplicates = DB::table('fantreffen_anmeldungen')
            ->select('user_id', DB::raw('COUNT(*) as count'))
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($userIdDuplicates->isNotEmpty()) {
            $list = $userIdDuplicates->pluck('user_id')->implode(', ');
            throw new RuntimeException(
                "Migration abgebrochen: Es existieren doppelte user_id-Werte in fantreffen_anmeldungen ({$list}). "
                .'Bitte manuell bereinigen, bevor der Unique-Index angelegt werden kann.'
            );
        }

        Schema::table('fantreffen_anmeldungen', function (Blueprint $table) {
            $table->unique('email');
            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fantreffen_anmeldungen', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->dropUnique(['user_id']);
        });
    }
};
