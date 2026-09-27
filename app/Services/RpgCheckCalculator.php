<?php

namespace App\Services;

use InvalidArgumentException;

final class RpgCheckCalculator
{
    public function base(string $type, int $attribute, ?int $skill, int $modifier): int
    {
        return match ($type) {
            'attribute' => 3 * $attribute + $modifier,
            'skill' => $attribute + ($skill ?? throw new InvalidArgumentException('Missing skill')) + $modifier,
            default => throw new InvalidArgumentException('Invalid check type'),
        };
    }

    public function roll(int $base, array $dice): array
    {
        if (count($dice) !== 2 || array_filter($dice, fn ($die) => ! is_int($die) || $die < 1 || $die > 6)) {
            throw new InvalidArgumentException('Two W6 required');
        }

        return ['die_one' => $dice[0], 'die_two' => $dice[1], 'raw_total' => array_sum($dice), 'total' => $base + array_sum($dice)];
    }

    public function result(int $raw, int $margin): string
    {
        return $margin >= 0
            ? ($raw === 12 ? 'critical_success' : 'success')
            : ($raw === 2 ? 'fumble' : 'failure');
    }
}
