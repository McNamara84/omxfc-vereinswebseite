<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Sitemap\SitemapGenerator;
use Spatie\Sitemap\Tags\Url;

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

    /**
     * Wichtige Seiten, die manuell zur Sitemap hinzugefügt werden sollen.
     * Diese werden zusätzlich zum Crawling eingefügt, falls der Crawler sie nicht findet.
     */
    protected array $manualUrls = [
        [
            'path' => '/maddrax-fantreffen-2026',
            'priority' => 0.9,
            'changeFreq' => Url::CHANGE_FREQUENCY_WEEKLY,
        ],
    ];

    public function handle(): int
    {
        $baseUrl = config('app.url');
        if (! $baseUrl || ! filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            $this->error('APP_URL is not set or is not a valid URL.');

            return self::FAILURE;
        }

        try {
            // Sitemap durch Crawling erstellen
            $sitemap = SitemapGenerator::create($baseUrl)->getSitemap();

            // Existierende URLs einmalig vor der Schleife sammeln
            $existingUrls = collect($sitemap->getTags())
                ->filter(fn ($tag) => $tag instanceof Url)
                ->pluck('url');

            // Wichtige Seiten manuell hinzufügen (falls nicht bereits gecrawlt)
            foreach ($this->manualUrls as $entry) {
                $fullUrl = rtrim($baseUrl, '/') . $entry['path'];

                if (! $existingUrls->contains($fullUrl)) {
                    $sitemap->add(
                        Url::create($fullUrl)
                            ->setPriority($entry['priority'])
                            ->setChangeFrequency($entry['changeFreq'])
                            ->setLastModificationDate(now())
                    );
                    $this->line("Added manual URL: {$entry['path']}");
                }
            }

            $sitemap->writeToFile(public_path('sitemap.xml'));
        } catch (\Throwable $e) {
            $this->error('Failed to generate sitemap: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info('Sitemap generated successfully.');

        return self::SUCCESS;
    }
}
