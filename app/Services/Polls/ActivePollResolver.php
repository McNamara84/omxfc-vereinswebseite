<?php

namespace App\Services\Polls;

use App\Enums\PollStatus;
use App\Models\Poll;

class ActivePollResolver
{
    public function current(): ?Poll
    {
        $poll = Poll::query()
            ->with('options')
            ->where('status', PollStatus::Active)
            ->orderByDesc('activated_at')
            ->first();

        return $poll ?: null;
    }
}
