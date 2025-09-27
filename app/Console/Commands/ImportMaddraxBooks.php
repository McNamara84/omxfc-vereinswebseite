<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Book;
use App\Enums\BookType;

class ImportMaddraxBooks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'books:import {--path=private/maddrax.json : Path to novels JSON file relative to storage/app} {--hardcovers-path=private/hardcovers.json : Path to hardcovers JSON file relative to storage/app} {--missionmars-path=private/missionmars.json : Path to Mission Mars novels JSON file relative to storage/app} {--volkdertiefe-path=private/volkdertiefe.json : Path to Das Volk der Tiefe novels JSON file relative to storage/app}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import books from maddrax.json, hardcovers.json, missionmars.json and volkdertiefe.json into the books table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $novelsPath = $this->option('path');
        $hardcoversPath = $this->option('hardcovers-path');
        $missionMarsPath = $this->option('missionmars-path');
        $volkDerTiefePath = $this->option('volkdertiefe-path');

        $novelsResult = $this->importFile($novelsPath, BookType::MaddraxDieDunkleZukunftDerErde);
        $hardcoversResult = $this->importFile($hardcoversPath, BookType::MaddraxHardcover);
        $missionMarsResult = $this->importFile($missionMarsPath, BookType::MissionMars);
        $volkDerTiefeResult = $this->importFile($volkDerTiefePath, BookType::DasVolkDerTiefe);

        return ($novelsResult || $hardcoversResult || $missionMarsResult || $volkDerTiefeResult) ? 0 : 1;
    }

    private function importFile(string $path, BookType $type): bool
    {
        $fullPath = storage_path("app/{$path}");

        if (!file_exists($fullPath)) {
            $this->error('Import for ' . $type->value . " failed: JSON file not found at {$fullPath}");
            return false;
        }

        $json = file_get_contents($fullPath);
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Import for ' . $type->value . ' failed: Invalid JSON - ' . json_last_error_msg());
            return false;
        }

        $bar = $this->output->createProgressBar(count($data));
        $bar->start();

        foreach ($data as $item) {
            $romanNumber = $item['nummer'] ?? null;
            $title = $item['titel'] ?? null;
            $authorData = $item['text'] ?? null;
            $author = is_array($authorData) ? implode(', ', $authorData) : $authorData;

            if (!$romanNumber || !$title) {
                $this->warn('Skipping invalid entry: ' . json_encode($item));
                $bar->advance();
                continue;
            }

            Book::updateOrCreate(
                ['roman_number' => $romanNumber, 'type' => $type->value],
                ['title' => $title, 'author' => $author, 'type' => $type->value]
            );

            $bar->advance();
        }

        $bar->finish();
        $this->info(PHP_EOL . 'Import for ' . $type->value . ' completed successfully.');

        return true;
    }
}
