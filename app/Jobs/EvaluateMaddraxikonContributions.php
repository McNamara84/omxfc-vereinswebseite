<?php

namespace App\Jobs;

use App\Models\MaddraxikonSyncState;
use App\Services\Maddraxikon\MaddraxikonRewardService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Tries(3)]
#[Timeout(300)]
#[Backoff([60, 300])]
class EvaluateMaddraxikonContributions implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly bool $force = false,
        public readonly ?int $contributionId = null
    ) {}

    public function handle(MaddraxikonRewardService $rewardService): void
    {
        $recoveryIsOpen = MaddraxikonSyncState::query()
            ->where(
                'wiki_key',
                (string) config('maddraxikon.wiki_key', 'maddraxikon-de')
            )
            ->whereNotNull('recovery_required_at')
            ->exists();

        if ($recoveryIsOpen) {
            return;
        }

        $rewardService->evaluate($this->force, $this->contributionId);
    }

    public function uniqueId(): string
    {
        $wikiKey = (string) config('maddraxikon.wiki_key', 'maddraxikon-de');

        return $this->contributionId === null
            ? $wikiKey
            : $wikiKey.':contribution:'.$this->contributionId;
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Maddraxikon-Auswertungsjob endgültig fehlgeschlagen.', [
            'wiki_key' => $this->uniqueId(),
            'exception' => $exception?->getMessage(),
        ]);
    }
}
