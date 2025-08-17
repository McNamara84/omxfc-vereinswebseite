<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $oldValues = [
            'Skript wird erstellt',
            'In Korrekturlesung',
            'Rollenbesetzung',
            'Aufnahmen in Arbeit',
            'Musikerstellung',
            'Audiobearbeitung gestartet',
            'Videobearbeitung gestartet',
            'Cover und Thumbnail in Arbeit',
            'Veröffentlichung geplant',
            'Veröffentlicht',
        ];

        $newValues = [
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

        $transition = array_unique(array_merge($oldValues, $newValues));
        DB::statement("ALTER TABLE audiobook_episodes MODIFY `status` ENUM('".implode("','", $transition)."') NOT NULL");

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

        DB::statement("ALTER TABLE audiobook_episodes MODIFY `status` ENUM('".implode("','", $newValues)."') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $oldValues = [
            'Skript wird erstellt',
            'In Korrekturlesung',
            'Rollenbesetzung',
            'Aufnahmen in Arbeit',
            'Musikerstellung',
            'Audiobearbeitung gestartet',
            'Videobearbeitung gestartet',
            'Cover und Thumbnail in Arbeit',
            'Veröffentlichung geplant',
            'Veröffentlicht',
        ];

        $newValues = [
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

        $transition = array_unique(array_merge($oldValues, $newValues));
        DB::statement("ALTER TABLE audiobook_episodes MODIFY `status` ENUM('".implode("','", $transition)."') NOT NULL");

        $mapping = [
            'Skripterstellung' => 'Skript wird erstellt',
            'Korrekturlesung' => 'In Korrekturlesung',
            'Aufnahmensammlung' => 'Aufnahmen in Arbeit',
            'Audiobearbeitung' => 'Audiobearbeitung gestartet',
            'Videobearbeitung' => 'Videobearbeitung gestartet',
            'Grafiken' => 'Cover und Thumbnail in Arbeit',
            'Veröffentlichungsplanung' => 'Veröffentlichung geplant',
            'Veröffentlichung' => 'Veröffentlicht',
        ];

        foreach ($mapping as $new => $old) {
            DB::table('audiobook_episodes')->where('status', $new)->update(['status' => $old]);
        }

        DB::statement("ALTER TABLE audiobook_episodes MODIFY `status` ENUM('".implode("','", $oldValues)."') NOT NULL");
    }
};
