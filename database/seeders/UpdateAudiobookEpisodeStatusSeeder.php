<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UpdateAudiobookEpisodeStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
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
            DB::table('audiobook_episodes')
                ->where('status', $old)
                ->update(['status' => $new]);
        }
    }
}
