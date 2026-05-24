<?php

namespace App\Services\Polls;

use App\Enums\PollStatus;
use App\Enums\PollVisibility;
use App\Models\Poll;

class ActivePollResolver
{
    public function current(): ?Poll
    {
        $poll = Poll::query()
            ->with('options')
            ->where('status', PollStatus::Active->value)
            ->whereIn('visibility', array_map(
                static fn (PollVisibility $visibility): string => $visibility->value,
                PollVisibility::cases(),
            ))
            ->orderByDesc('activated_at')
            ->first();

        return $poll ?: null;
    }
}
