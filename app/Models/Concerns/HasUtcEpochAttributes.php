<?php

namespace App\Models\Concerns;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait HasUtcEpochAttributes
{
    protected function utcEpochAttribute(string $column): Attribute
    {
        $epochColumn = $column.'_epoch';

        return Attribute::make(
            get: function (
                mixed $value,
                array $attributes,
            ) use ($epochColumn): ?CarbonImmutable {
                if (isset($attributes[$epochColumn])) {
                    return CarbonImmutable::createFromTimestampUTC(
                        (int) $attributes[$epochColumn],
                    );
                }

                if ($value === null) {
                    return null;
                }

                return CarbonImmutable::parse(
                    $value,
                    (string) config('app.timezone', 'UTC'),
                );
            },
            set: function (mixed $value) use (
                $column,
                $epochColumn,
            ): array {
                if ($value === null) {
                    return [
                        $column => null,
                        $epochColumn => null,
                    ];
                }

                $instant = $value instanceof DateTimeInterface
                    ? CarbonImmutable::instance($value)
                    : CarbonImmutable::parse(
                        $value,
                        (string) config('app.timezone', 'UTC'),
                    );

                return [
                    $column => $instant->utc()->format('Y-m-d H:i:s'),
                    $epochColumn => $instant->getTimestamp(),
                ];
            },
        );
    }
}
