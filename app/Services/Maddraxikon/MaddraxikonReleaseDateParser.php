<?php

namespace App\Services\Maddraxikon;

use App\Exceptions\MaddraxikonCrawlException;
use Carbon\CarbonImmutable;
use Throwable;

class MaddraxikonReleaseDateParser
{
    private const GERMAN_MONTHS = [
        'januar' => 'January',
        'februar' => 'February',
        'märz' => 'March',
        'maerz' => 'March',
        'april' => 'April',
        'mai' => 'May',
        'juni' => 'June',
        'juli' => 'July',
        'august' => 'August',
        'september' => 'September',
        'oktober' => 'October',
        'november' => 'November',
        'dezember' => 'December',
    ];

    public function parse(string $value): CarbonImmutable
    {
        $normalized = preg_replace_callback(
            '/(?<!\p{L})(Januar|Februar|März|Maerz|April|Mai|Juni|Juli|August|September|Oktober|November|Dezember)(?!\p{L})/iu',
            static fn (array $matches): string => self::GERMAN_MONTHS[mb_strtolower($matches[1], 'UTF-8')],
            trim($value),
        );
        $normalized ??= trim($value);

        try {
            $timezone = (string) config('maddraxikon.timezone', 'Europe/Berlin');

            if (preg_match('/^[A-Z][a-z]+\s+\d{4}$/', $normalized) === 1) {
                return CarbonImmutable::createFromFormat('!F Y', $normalized, $timezone)
                    ->startOfDay();
            }

            return CarbonImmutable::parse($normalized, $timezone)->startOfDay();
        } catch (Throwable $exception) {
            throw new MaddraxikonCrawlException(
                "Veröffentlichungsdatum '{$value}' konnte nicht sicher ausgewertet werden.",
                previous: $exception,
            );
        }
    }
}
