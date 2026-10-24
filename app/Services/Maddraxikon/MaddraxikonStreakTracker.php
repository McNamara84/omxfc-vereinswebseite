<?php

namespace App\Services\Maddraxikon;

use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonContribution;
use App\Models\MaddraxikonStreakDay;
use App\Models\MaddraxikonStreakState;

final class MaddraxikonStreakTracker
{
    public function __construct(private readonly MaddraxikonStreakSettings $settings) {}

    /** Called inside the import transaction, after its sync/team locks. */
    public function record(MaddraxikonContribution $source, MaddraxikonAccountLink $link): void
    {
        $start = $this->settings->start();
        if (! $start || $source->occurredAtUtc()->lt($start)) {
            return;
        }
        $this->settings->pinStart();
        $date = $source->occurredAtUtc()->setTimezone($this->settings->timezone())->toDateString();
        $source->forceFill([
            'streak_activity_date' => $date,
            'streak_verified_at_epoch' => $link->streak_verified_at_epoch ?? $link->verified_at?->timestamp,
            'streak_status' => $source->occurredAtUtc()->timestamp < ($link->streak_verified_at_epoch ?? $link->verified_at?->timestamp ?? PHP_INT_MAX) ? 'excluded' : ($source->streak_status ?? 'pending'),
        ])->save();
        MaddraxikonStreakDay::query()->firstOrCreate(
            ['wiki_key' => $source->wiki_key, 'user_id' => $source->user_id, 'activity_date' => $date],
            ['starts_at_epoch' => $start->timestamp],
        );
        $this->dirty($source->wiki_key, $source->user_id, $date);
    }

    public function dirty(string $wiki, int $userId, string $date): void
    {
        $state = MaddraxikonStreakState::query()->firstOrCreate(['wiki_key' => $wiki, 'user_id' => $userId]);
        if ($state->dirty_from === null || $date < $state->dirty_from) {
            $state->update(['dirty_from' => $date]);
        }
    }
}
