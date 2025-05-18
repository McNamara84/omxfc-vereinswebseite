<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\Book;

class ImportMaddraxBooks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'books:import {--path=private/maddrax.json : Path to JSON file relative to storage/app}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import books from maddrax.json into the books table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $path = $this->option('path');
        $fullPath = storage_path("app/{$path}");

        if (!file_exists($fullPath)) {
            $this->error("JSON file not found at {$fullPath}");
            return 1;
        }

        $json = file_get_contents($fullPath);
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Invalid JSON: ' . json_last_error_msg());
            return 1;
        }

        $bar = $this->output->createProgressBar(count($data));
        $bar->start();

        foreach ($data as $item) {
            $romanNumber = $item['nummer'] ?? null;
            $title = $item['titel'] ?? null;
            $author = is_array($item['text']) ? implode(', ', $item['text']) : ($item['text'] ?? null);

            if (!$romanNumber || !$title) {
                $this->warn('Skipping invalid entry: ' . json_encode($item));
                $bar->advance();
                continue;
            }

            Book::updateOrCreate(
                ['roman_number' => $romanNumber],
                ['title' => $title, 'author' => $author]
            );

            $bar->advance();
        }

        $bar->finish();
        $this->info(PHP_EOL . 'Import completed successfully.');

        return 0;
    }
}
