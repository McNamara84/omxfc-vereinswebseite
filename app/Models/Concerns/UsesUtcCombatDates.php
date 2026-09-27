<?php

namespace App\Models\Concerns;

use Carbon\Carbon;
use DateTimeInterface;

/** Combat deadlines remain unambiguous across the Europe/Berlin DST fold. */
trait UsesUtcCombatDates
{
    protected function asDateTime($value)
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->utc();
        }
        if (is_numeric($value)) {
            return Carbon::createFromTimestampUTC($value);
        }

        return Carbon::parse($value, 'UTC');
    }

    public function fromDateTime($value)
    {
        return empty($value) ? $value : $this->asDateTime($value)->utc()->format($this->getDateFormat());
    }

    public function freshTimestamp()
    {
        return Carbon::now('UTC');
    }
}
