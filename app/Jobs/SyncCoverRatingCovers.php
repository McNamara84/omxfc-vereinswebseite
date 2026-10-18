<?php

namespace App\Jobs;

use App\Models\Book;
use App\Services\CoverRatings\BookCoverSyncRunner;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SyncCoverRatingCovers implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 7200;

    public int $timeout = 330;

    public bool $failOnTimeout = true;

    public function handle(BookCoverSyncRunner $runner): void
    {
        if (! config('cover-ratings.sync_enabled')) {
            return;
        }

        $counts = $runner->run(Book::query());
        Log::info('Cover- und Schlagwortabgleich abgeschlossen.', $counts);
        if ($counts['failed'] > 0 || ($counts['keywords_failed'] ?? 0) > 0) {
            throw new RuntimeException('Cover- oder Schlagwortabgleich teilweise fehlgeschlagen.');
        }
    }

    public function uniqueId(): string
    {
        return 'cover-ratings:sync-covers';
    }
}
