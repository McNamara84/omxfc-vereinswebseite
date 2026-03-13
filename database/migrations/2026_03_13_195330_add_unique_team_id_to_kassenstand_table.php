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
        // Duplikate bereinigen: nur die neueste Zeile pro Team behalten
        $duplicates = DB::table('kassenstand')
            ->select('team_id')
            ->groupBy('team_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('team_id');

        foreach ($duplicates as $teamId) {
            $keepId = DB::table('kassenstand')
                ->where('team_id', $teamId)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->value('id');

            DB::table('kassenstand')
                ->where('team_id', $teamId)
                ->where('id', '!=', $keepId)
                ->delete();
        }

        Schema::table('kassenstand', function (Blueprint $table) {
            $table->unique('team_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kassenstand', function (Blueprint $table) {
            $table->dropUnique(['team_id']);
        });
    }
};
