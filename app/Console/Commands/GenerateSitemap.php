<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Sitemap\SitemapGenerator;

class GenerateSitemap extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'sitemap:generate';

    /**
     * The console command description.
     */
    protected $description = 'Generate the sitemap for publicly accessible pages.';

    public function handle(): int
    {
        $baseUrl = config('app.url');
        if (! $baseUrl || ! filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            $this->error('APP_URL is not set or is not a valid URL.');

            return self::FAILURE;
        }

        try {
            SitemapGenerator::create($baseUrl)
                ->writeToFile(public_path('sitemap.xml'));
        } catch (\Throwable $e) {
            $this->error('Failed to generate sitemap: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info('Sitemap generated successfully.');

        return self::SUCCESS;
    }
}
