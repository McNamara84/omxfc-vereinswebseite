<?php

namespace Tests\Concerns;

use App\Models\MemberClientSnapshot;
use Carbon\Carbon;
use DateTimeInterface;

trait CreatesMemberClientSnapshot
{
    protected function createSnapshot(int $userId, ?string $userAgent, Carbon|DateTimeInterface $lastSeenAt): void
    {
        MemberClientSnapshot::updateOrCreate(
            [
                'user_id' => $userId,
                'user_agent_hash' => MemberClientSnapshot::hashUserAgent($userAgent),
            ],
            [
                'user_agent' => $userAgent,
                'last_seen_at' => $lastSeenAt,
            ]
        );
    }
}
