<?php

namespace App\Services\Maddraxikon;

final class MaddraxikonStreakCalculator
{
    /** Unknown predecessors propagate until a confirmed inactive day. */
    public function length(string $status, ?int $previousLength): ?int
    {
        return match ($status) {
            'inactive' => 0,
            'valid' => $previousLength === null ? null : $previousLength + 1,
            default => null,
        };
    }
}
