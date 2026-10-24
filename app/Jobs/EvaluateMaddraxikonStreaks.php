<?php

namespace App\Jobs;

use App\Services\Maddraxikon\MaddraxikonStreakService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;

#[Tries(3)]
#[Timeout(300)]
#[Backoff([60, 300])]
class EvaluateMaddraxikonStreaks implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 3600;

    public function __construct(public readonly bool $recheck = false) {}

    public function handle(MaddraxikonStreakService $service): void
    {
        $service->run($this->recheck);
    }

    public function uniqueId(): string
    {
        return config('maddraxikon.wiki_key').':streaks:'.($this->recheck ? 'recheck' : 'evaluate');
    }
}
