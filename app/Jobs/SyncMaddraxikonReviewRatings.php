<?php

namespace App\Jobs;

use App\Services\Maddraxikon\MaddraxikonRatingSynchronizer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Tries(3)]
#[Timeout(120)]
#[Backoff([60, 300])]
class SyncMaddraxikonReviewRatings implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 900;

    public function handle(MaddraxikonRatingSynchronizer $synchronizer): void
    {
        $synchronizer->sync();
    }

    public function uniqueId(): string
    {
        return (string) config('maddraxikon.wiki_key', 'maddraxikon-de').':review-ratings';
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Maddraxikon-Bewertungssync-Job endgültig fehlgeschlagen.', [
            'wiki_key' => (string) config('maddraxikon.wiki_key', 'maddraxikon-de'),
            'exception_category' => $exception ? class_basename($exception) : null,
        ]);
    }
}
