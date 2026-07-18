<?php

namespace App\Jobs;

use App\Services\Maddraxikon\MaddraxikonContributionImporter;
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
class SyncMaddraxikonContributions implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 900;

    public function __construct(public readonly bool $force = false) {}

    public function handle(MaddraxikonContributionImporter $importer): void
    {
        $importer->sync($this->force);
    }

    public function uniqueId(): string
    {
        return (string) config('maddraxikon.wiki_key', 'maddraxikon-de');
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Maddraxikon-Sync-Job endgültig fehlgeschlagen.', [
            'wiki_key' => $this->uniqueId(),
            'exception' => $exception?->getMessage(),
        ]);
    }
}
