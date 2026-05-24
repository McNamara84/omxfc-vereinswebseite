<?php

use App\Support\BundledDownloadLocator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->rulebookDownloads() as $download) {
            $existing = DB::table('downloads')
                ->where('slug', $download['slug'])
                ->first();

            $payload = [
                'title' => $download['title'],
                'description' => $download['description'],
                'category' => $download['category'],
                'file_path' => $download['file_path'],
                'original_filename' => $download['original_filename'],
                'mime_type' => $download['mime_type'],
                'file_size' => $download['file_size'],
                'is_active' => true,
                'sort_order' => $download['sort_order'],
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('downloads')
                    ->where('id', $existing->id)
                    ->update($payload);

                continue;
            }

            DB::table('downloads')->insert([
                'title' => $download['title'],
                'slug' => $download['slug'],
                'description' => $download['description'],
                'category' => $download['category'],
                'file_path' => $download['file_path'],
                'original_filename' => $download['original_filename'],
                'mime_type' => $download['mime_type'],
                'file_size' => $download['file_size'],
                'is_active' => true,
                'sort_order' => $download['sort_order'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('downloads')
            ->whereIn('slug', ['rollenspiel-regelwerk-2001', 'rollenspiel-regelwerk-2007'])
            ->delete();
    }

    /**
     * @return array<int, array<string, int|string|null>>
     */
    private function rulebookDownloads(): array
    {
        return [
            [
                'title' => 'Rollenspiel-Regelwerk 2001',
                'slug' => 'rollenspiel-regelwerk-2001',
                'description' => 'Rollenspiel-Regelwerk von 2001 von Uwe Simon.',
                'category' => 'Rollenspiel-Regelwerke',
                'file_path' => 'downloads/Regelwerk2001.pdf',
                'original_filename' => 'Regelwerk2001.pdf',
                'mime_type' => 'application/pdf',
                'file_size' => BundledDownloadLocator::fileSize('downloads/Regelwerk2001.pdf'),
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
                'sort_order' => 1,
            ],
        ];
    }
};