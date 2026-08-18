<?php

namespace App\Console\Commands;

use App\Enums\BookType;
use App\Models\Book;
use App\Services\CoverRatings\BookCoverSyncRunner;
use Illuminate\Console\Command;
use Throwable;

class SyncCoverRatingCovers extends Command
{
    protected $signature = 'cover-ratings:sync-covers
        {--dry-run : Änderungen prüfen, aber nichts speichern}
        {--book= : Nur eine lokale Buch-ID synchronisieren}
        {--series= : Nur einen stabilen Serien-Key synchronisieren}
        {--force : Geänderte Titelbilder und identische Fingerprints erneut verarbeiten}';

    protected $description = 'Synchronisiert Cover aus dem Maddraxikon für die Cover-Bewertungen.';

    public function handle(BookCoverSyncRunner $runner): int
    {
        if (! config('cover-ratings.sync_enabled') && ! $this->option('dry-run')) {
            $this->warn('Der Cover-Sync ist deaktiviert (COVER_RATINGS_SYNC_ENABLED=false).');

            return self::FAILURE;
        }

        $query = Book::query();

        if ($bookId = $this->option('book')) {
            if (! ctype_digit((string) $bookId) || (int) $bookId < 1) {
                $this->error('--book muss eine positive lokale Buch-ID sein.');

                return self::INVALID;
            }

            $query->whereKey((int) $bookId);
        }

        if ($seriesKey = $this->option('series')) {
            $type = BookType::fromKey((string) $seriesKey);

            if (! $type) {
                $this->error('Unbekannter Serien-Key. Erlaubt: '.implode(', ', array_map(
                    fn (BookType $case): string => $case->key(),
                    BookType::cases(),
                )));

                return self::INVALID;
            }

            $query->where('type', $type);
        }

        try {
            $counts = $runner->run(
                $query,
                force: (bool) $this->option('force'),
                dryRun: (bool) $this->option('dry-run'),
                report: function (Book $book, string $result): void {
                    if (in_array($result, ['missing', 'changed', 'failed'], true)) {
                        $this->line(sprintf(
                            '[%s] %s #%d – %s',
                            strtoupper($result),
                            $book->type->label(),
                            $book->roman_number,
                            $book->title,
                        ));
                    }
                },
            );
        } catch (Throwable $exception) {
            $this->error('Cover-Synchronisation fehlgeschlagen ('.class_basename($exception).').');

            return self::FAILURE;
        }

        $this->table(['Ergebnis', 'Anzahl'], collect($counts)
            ->map(fn (int $count, string $result): array => [$result, $count])
            ->values()
            ->all());

        if ($this->option('dry-run')) {
            $this->comment('Dry-Run: Es wurden keine Dateien oder Datenbankwerte verändert.');
        }

        return $counts['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
