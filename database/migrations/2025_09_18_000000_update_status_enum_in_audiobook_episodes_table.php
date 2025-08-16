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

        $mapping = [
            'Skript wird erstellt' => 'Skripterstellung',
            'In Korrekturlesung' => 'Korrekturlesung',
            'Aufnahmen in Arbeit' => 'Aufnahmensammlung',
            'Audiobearbeitung gestartet' => 'Audiobearbeitung',
            'Videobearbeitung gestartet' => 'Videobearbeitung',
            'Cover und Thumbnail in Arbeit' => 'Grafiken',
            'Veröffentlichung geplant' => 'Veröffentlichungsplanung',
            'Veröffentlicht' => 'Veröffentlichung',
        ];

        foreach ($mapping as $old => $new) {
            DB::table('audiobook_episodes')->where('status', $old)->update(['status' => $new]);
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

        $mapping = [
            'Skripterstellung' => 'Skript wird erstellt',
            'Korrekturlesung' => 'In Korrekturlesung',
            'Rollenbesetzung' => 'Aufnahmen in Arbeit',
            'Aufnahmensammlung' => 'Aufnahmen in Arbeit',
            'Musikerstellung' => 'Audiobearbeitung gestartet',
            'Audiobearbeitung' => 'Audiobearbeitung gestartet',
            'Videobearbeitung' => 'Videobearbeitung gestartet',
            'Grafiken' => 'Cover und Thumbnail in Arbeit',
            'Veröffentlichungsplanung' => 'Veröffentlichung geplant',
            'Veröffentlichung' => 'Veröffentlicht',
        ];

        foreach ($mapping as $new => $old) {
            DB::table('audiobook_episodes')->where('status', $new)->update(['status' => $old]);
        }
    }
};
