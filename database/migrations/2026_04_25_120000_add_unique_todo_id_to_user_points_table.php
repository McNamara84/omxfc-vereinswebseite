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
        // Duplikate bereinigen: pro todo_id nur den ältesten Eintrag (min(id)) behalten,
        // damit doppelte Verifizierungen rückwirkend keine Mehrfach-Baxx erzeugen.
        // Set-basiertes DELETE statt Loop pro todo_id (vermeidet N+1 auf großen Tabellen).
        // Die zusätzliche Wrapping-Subquery ist nötig, damit MySQL/MariaDB den DELETE
        // nicht mit dem Fehler "You can't specify target table ... for update in FROM clause"
        // ablehnt; SQLite akzeptiert die Form ebenfalls.
        DB::statement(
            'DELETE FROM user_points
             WHERE todo_id IS NOT NULL
               AND id NOT IN (
                   SELECT keep_id FROM (
                       SELECT MIN(id) AS keep_id
                       FROM user_points
                       WHERE todo_id IS NOT NULL
                       GROUP BY todo_id
                   ) keep_ids
               )'
        );

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
