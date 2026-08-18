<?php

namespace App\Jobs;

use App\Models\Book;
use App\Services\CoverRatings\BookCoverSyncRunner;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncCoverRatingCovers implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 7200;

    public function handle(BookCoverSyncRunner $runner): void
    {
        if (! config('cover-ratings.sync_enabled')) {
            return;
        }

        $runner->run(Book::query());
    }

    public function uniqueId(): string
    {
        return 'cover-ratings:sync-covers';
    }
}
