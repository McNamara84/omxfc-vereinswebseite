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
        $duplicates = DB::table('fantreffen_anmeldungen')
            ->select('email')
            ->groupBy('email')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('email');

        foreach ($duplicates as $email) {
            $keepId = DB::table('fantreffen_anmeldungen')
                ->where('email', $email)
                ->orderBy('id')
                ->value('id');

            DB::table('fantreffen_anmeldungen')
                ->where('email', $email)
                ->where('id', '!=', $keepId)
                ->delete();
        }

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
