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
        // Duplikate bereinigen: pro todo_id nur die älteste Gutschrift behalten,
        // damit doppelte Verifizierungen rückwirkend keine Mehrfach-Baxx erzeugen.
        $duplicates = DB::table('user_points')
            ->select('todo_id')
            ->whereNotNull('todo_id')
            ->groupBy('todo_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('todo_id');

        foreach ($duplicates as $todoId) {
            $keepId = DB::table('user_points')
                ->where('todo_id', $todoId)
                ->orderBy('created_at')
                ->orderBy('id')
                ->value('id');

            DB::table('user_points')
                ->where('todo_id', $todoId)
                ->where('id', '!=', $keepId)
                ->delete();
        }

        Schema::table('user_points', function (Blueprint $table) {
            // todo_id ist nullable; SQL-Standard erlaubt mehrere NULLs in einem
            // UNIQUE-Index (gilt für MySQL/MariaDB und SQLite gleichermaßen).
            $table->unique('todo_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_points', function (Blueprint $table) {
            $table->dropUnique(['todo_id']);
        });
    }
};
