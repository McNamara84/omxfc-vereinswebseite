<?php

namespace Database\Seeders;

use App\Models\Download;
use App\Models\Reward;
use Illuminate\Database\Seeder;

class DownloadSeeder extends Seeder
{
    /**
     * Migrate existing hardcoded downloads into the downloads table
     * and link them to their corresponding rewards.
     *
     * Safe to run multiple times (upserts by title).
     */
    public function run(): void
    {
        $downloads = [
            [
                'title' => 'Bauanleitung Euphoriewurm',
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
                'description' => 'Exklusive MADDRAX-Kurzgeschichte "Das Flüstern der Vergangenheit" von Max T. Hardwet.',
                'category' => 'Fanstories',
                'file_path' => 'downloads/DasFlüsternDerVergangenheit.pdf',
                'original_filename' => 'Das Flüstern der Vergangenheit.pdf',
                'mime_type' => 'application/pdf',
                'reward_slug' => 'downloads-kurzgeschichte-1',
                'sort_order' => 0,
            ],
        ];

        foreach ($downloads as $data) {
            $rewardSlug = $data['reward_slug'];
            unset($data['reward_slug']);

            $download = Download::updateOrCreate(
                ['title' => $data['title']],
                $data
            );

            // Link the corresponding reward to this download
            if ($rewardSlug) {
                Reward::where('slug', $rewardSlug)
                    ->whereNull('download_id')
                    ->update(['download_id' => $download->id]);
            }
        }
    }
}
