<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('audiobook_episodes')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        $statuses = [
            'Skripterstellung',
            'Korrekturlesung',
            'Rollenbesetzung',
            'Aufnahmensammlung',
            'Musikerstellung',
            'Audiobearbeitung',
            'Videobearbeitung',
            'Grafiken',
            'Veröffentlichungsplanung',
            'Veröffentlichung',
        ];

        if ($driver !== 'sqlite') {
            $list = "'" . implode("','", $statuses) . "'";
            DB::statement("ALTER TABLE audiobook_episodes MODIFY COLUMN status ENUM($list) NOT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('audiobook_episodes')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        $statuses = [
            'Skript wird erstellt',
            'In Korrekturlesung',
            'Aufnahmen in Arbeit',
            'Audiobearbeitung gestartet',
            'Videobearbeitung gestartet',
            'Cover und Thumbnail in Arbeit',
            'Veröffentlichung geplant',
            'Veröffentlicht',
        ];

        if ($driver !== 'sqlite') {
            $list = "'" . implode("','", $statuses) . "'";
            DB::statement("ALTER TABLE audiobook_episodes MODIFY COLUMN status ENUM($list) NOT NULL");
        }
    }
};
