<?php

namespace App\Console\Commands;

use App\Models\Book;
use App\Services\Maddraxikon\MaddraxikonBookMapper;
use Illuminate\Console\Command;
use Throwable;

class MapMaddraxikonReviewBooksCommand extends Command
{
    protected $signature = 'maddraxikon:map-review-books
        {--dry-run : Zuordnungen prüfen, aber nicht lokal speichern}
        {--all : Auch Bücher ohne vorhandene Rezension prüfen}';

    protected $description = 'Ordnet lokale Rezensionsbücher ihren kanonischen Maddraxikon-Seiten zu.';

    public function handle(MaddraxikonBookMapper $mapper): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $query = Book::query()
            ->whereNotNull('maddraxikon_page_title')
            ->orderBy('id');

        if (! $this->option('all')) {
            $query->whereHas('reviews');
        }

        $mapped = 0;
        $unchanged = 0;
        $missing = 0;

        try {
            $query->eachById(function (Book $book) use (
                $mapper,
                $dryRun,
                &$mapped,
                &$unchanged,
                &$missing,
            ): void {
                $mapping = $mapper->resolve((string) $book->maddraxikon_page_title);

                if ($mapping === null) {
                    $missing++;
                    $this->warn(sprintf(
                        'Keine eindeutige Maddraxikon-Seite für Buch-ID %d (%s).',
                        $book->id,
                        $book->type->value,
                    ));

                    return;
                }

                if (
                    $book->maddraxikon_page_id === $mapping->pageId
                    && $book->maddraxikon_page_title === $mapping->pageTitle
                ) {
                    $unchanged++;

                    if (! $dryRun) {
                        $book->forceFill([
                            'maddraxikon_page_verified_at' => now(),
                        ])->save();
                    }

                    return;
                }

                $mapped++;

                if (! $dryRun) {
                    $book->forceFill([
                        'maddraxikon_page_id' => $mapping->pageId,
                        'maddraxikon_page_title' => $mapping->pageTitle,
                        'maddraxikon_page_verified_at' => now(),
                    ])->save();
                }
            }, 100);
        } catch (Throwable $exception) {
            $this->error(
                'Maddraxikon-Buchzuordnung fehlgeschlagen ('.class_basename($exception).').'
            );

            return self::FAILURE;
        }

        $this->table(['Ergebnis', 'Anzahl'], [
            ['Neu/aktualisiert', $mapped],
            ['Unverändert', $unchanged],
            ['Nicht zugeordnet', $missing],
        ]);

        if ($dryRun) {
            $this->comment('Dry-Run: Es wurden keine lokalen Zuordnungen gespeichert.');
        }

        return $missing === 0 ? self::SUCCESS : self::FAILURE;
    }
}
