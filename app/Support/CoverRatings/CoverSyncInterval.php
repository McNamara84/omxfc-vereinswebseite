<?php

namespace App\Support\CoverRatings;

final class CoverSyncInterval
{
    public const DEFAULT_HOURS = 24;

    /** @var list<int> */
    public const ALLOWED_HOURS = [1, 2, 3, 4, 6, 8, 12, 24];

    public static function normalize(int $hours): int
    {
        return in_array($hours, self::ALLOWED_HOURS, true)
            ? $hours
            : self::DEFAULT_HOURS;
    }
}
