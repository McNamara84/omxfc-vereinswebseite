<?php

namespace App\Console\Commands;

use App\Enums\BookType;
use App\Exceptions\MaddraxikonCrawlException;
use App\Services\Maddraxikon\MaddraxikonCandidateStore;
use App\Services\Maddraxikon\MaddraxikonCrawler;
use App\Services\Maddraxikon\MaddraxikonRefreshCoordinator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RefreshMaddraxBooks extends Command
{
    protected $signature = 'books:refresh
        {--series=all : Serien-Key oder all}
        {--dry-run : Kandidat erzeugen und validieren, aber nicht freigeben}
        {--promote= : Bereits validierten Kandidaten anhand seiner ID freigeben}';

    protected $description = 'Crawl, validate and atomically refresh Maddraxikon book data';

    public function handle(
        MaddraxikonCrawler $crawler,
        MaddraxikonCandidateStore $candidates,
        MaddraxikonRefreshCoordinator $coordinator,
    ): int {
        set_time_limit(1800);

        if ($this->option('dry-run') && is_string($this->option('promote'))) {
            $this->error('--dry-run und --promote können nicht kombiniert werden.');

            return self::INVALID;
        }

        $lock = Cache::lock(
            'maddraxikon-books-refresh',
            max(60, (int) config('maddraxikon.crawler.lock_seconds', 3600)),
        );
        $lockAcquired = false;

        try {
            $lockAcquired = $lock->get();

            if (! $lockAcquired) {
                $this->error('Ein anderer Maddraxikon-Romandaten-Refresh läuft bereits.');

                return self::FAILURE;
            }

            $promote = $this->option('promote');

            if (is_string($promote) && trim($promote) !== '') {
                return $this->promote($coordinator, trim($promote));
            }

            $types = $this->selectedTypes((string) $this->option('series'));
            $bars = [];
            $datasets = $crawler->crawl(
                $types,
                function (BookType $type, int $total) use (&$bars): void {
                    $this->info("Crawle {$type->label()} ({$total} Artikel) …");
                    $bars[$type->key()] = $this->output->createProgressBar($total);
                    $bars[$type->key()]->start();
                },
                function (BookType $type) use (&$bars): void {
                    $bars[$type->key()]?->advance();
                },
            );

            foreach ($bars as $bar) {
                $bar->finish();
                $this->newLine();
            }

            $candidate = $candidates->stage($datasets);
            $this->info("Kandidat: {$candidate['id']}");
            $this->line("SHA-256: {$candidate['hash']}");

            if ($this->option('dry-run')) {
                $this->info('Dry-Run erfolgreich: Aktiver Datenbank-Snapshot, Buchdaten und Cache blieben unverändert.');

                return self::SUCCESS;
            }

            return $this->promote($coordinator, $candidate['id']);
        } catch (MaddraxikonCrawlException $exception) {
            Log::error('Maddraxikon-Romandaten-Refresh abgelehnt.', [
                'message' => $exception->getMessage(),
                'url' => $exception->url,
                'status' => $exception->statusCode,
            ]);
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (\Throwable $exception) {
            report($exception);
            $this->error('Maddraxikon-Romandaten konnten nicht sicher aktualisiert werden: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            if ($lockAcquired) {
                $lock->release();
            }
        }
    }

    /** @return list<BookType> */
    private function selectedTypes(string $series): array
    {
        if ($series === 'all') {
            return BookType::cases();
        }

        $type = BookType::fromKey($series);

        if ($type === null) {
            throw new MaddraxikonCrawlException(
                "Unbekannte Reihe {$series}. Erlaubt: all, ".
                implode(', ', array_map(static fn (BookType $item): string => $item->key(), BookType::cases()))
            );
        }

        return [$type];
    }

    private function promote(MaddraxikonRefreshCoordinator $coordinator, string $candidateId): int
    {
        $counts = $coordinator->promote($candidateId);

        foreach ($counts as $seriesKey => $count) {
            $this->info("{$seriesKey}.json und Datenbank sicher aktualisiert ({$count} Datensätze).");
        }

        return self::SUCCESS;
    }
}
