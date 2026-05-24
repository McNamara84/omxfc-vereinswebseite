<?php

namespace Database\Seeders;

use App\Models\Download;
use App\Support\BundledDownloadLocator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DownloadSeeder extends Seeder
{
    /**
     * Migrate existing hardcoded downloads into the downloads table
     * and link them to their corresponding rewards.
     *
     * Safe to run multiple times (upserts by file_path).
     */
    public function run(): void
    {
        $downloads = [
            [
                'title' => 'Bauanleitung Euphoriewurm',
                'slug' => 'bauanleitung-euphoriewurm',
                'description' => 'Exklusive Klemmbaustein-Bauanleitung für den Euphoriewurm aus MX 658.',
                'category' => 'Klemmbaustein-Anleitungen',
                'file_path' => 'downloads/BauanleitungEuphoriewurmV2.pdf',
                'original_filename' => 'Bauanleitung Euphoriewurm.pdf',
                'mime_type' => 'application/pdf',
                'reward_slug' => 'downloads-euphoriewurm',
                'sort_order' => 0,
            ],
            [
                'title' => 'Bauanleitung Prototyp XP-1',
                'slug' => 'bauanleitung-prototyp-xp-1',
                'description' => 'Exklusive Klemmbaustein-Bauanleitung für den Amphibienpanzer Prototyp XP-1.',
                'category' => 'Klemmbaustein-Anleitungen',
                'file_path' => 'downloads/BauanleitungProtoV11.pdf',
                'original_filename' => 'Bauanleitung Prototyp XP-1.pdf',
                'mime_type' => 'application/pdf',
                'reward_slug' => 'downloads-proto',
                'sort_order' => 1,
            ],
            [
                'title' => 'Das Flüstern der Vergangenheit',
                'slug' => 'das-fluestern-der-vergangenheit',
                'description' => 'Exklusive MADDRAX-Kurzgeschichte "Das Flüstern der Vergangenheit" von Max T. Hardwet.',
                'category' => 'Fanstories',
                'file_path' => 'downloads/DasFlüsternDerVergangenheit.pdf',
                'original_filename' => 'Das Flüstern der Vergangenheit.pdf',
                'mime_type' => 'application/pdf',
                'reward_slug' => 'downloads-kurzgeschichte-1',
                'sort_order' => 0,
            ],
            [
                'title' => 'Rollenspiel-Regelwerk 2001',
                'slug' => 'rollenspiel-regelwerk-2001',
                'description' => 'Rollenspiel-Regelwerk von 2001 von Uwe Simon.',
                'category' => 'Rollenspiel-Regelwerke',
                'file_path' => 'downloads/Regelwerk2001.pdf',
                'original_filename' => 'Regelwerk2001.pdf',
                'mime_type' => 'application/pdf',
                'file_size' => BundledDownloadLocator::fileSize('downloads/Regelwerk2001.pdf'),
                'reward_slug' => null,
                'sort_order' => 0,
            ],
            [
                'title' => 'Rollenspiel-Regelwerk 2007',
                'slug' => 'rollenspiel-regelwerk-2007',
                'description' => 'Rollenspiel-Regelwerk von 2007 von Thomas Biskup.',
                'category' => 'Rollenspiel-Regelwerke',
                'file_path' => 'downloads/Regelwerk2007.pdf',
                'original_filename' => 'Regelwerk2007.pdf',
                'mime_type' => 'application/pdf',
                'file_size' => BundledDownloadLocator::fileSize('downloads/Regelwerk2007.pdf'),
                'reward_slug' => null,
                'sort_order' => 1,
            ],
        ];

        foreach ($downloads as $data) {
            $rewardSlug = $data['reward_slug'];
            unset($data['reward_slug']);

            $download = Download::updateOrCreate(
                ['file_path' => $data['file_path']],
                $data
            );

            // Link the corresponding reward to this download
            if ($rewardSlug) {
                DB::table('rewards')
                    ->where('slug', $rewardSlug)
                    ->whereNull('download_id')
                    ->update(['download_id' => $download->id]);
            }

            // In lokaler Umgebung Dummy-Dateien anlegen, damit Downloads nicht fehlschlagen.
            // Nicht in testing: Tests nutzen Storage::fake('private') und erzeugen Dateien selbst.
            if (App::environment('local') && ! Storage::disk('private')->exists($data['file_path'])) {
                Storage::disk('private')->put($data['file_path'], 'Dummy-Datei für Entwicklung');
            }
        }
    }
}
