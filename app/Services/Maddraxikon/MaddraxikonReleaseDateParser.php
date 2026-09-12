<?php

namespace App\Services\Maddraxikon;

use App\Exceptions\MaddraxikonCrawlException;
use Carbon\CarbonImmutable;
use Throwable;
use UnexpectedValueException;

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

    private const SUPPORTED_FORMATS = [
        '/^\d{4}-\d{2}-\d{2}$/' => '!Y-m-d',
        '/^\d{4}-\d{2}$/' => '!Y-m',
        '/^\d{4}-\d$/' => '!Y-n',
        '/^\d{4}$/' => '!Y',
        '/^\d{1,2}\.\d{1,2}\.\d{4}$/' => '!j.n.Y',
        '/^\d{1,2}\.\s+[A-Z][a-z]+\s+\d{4}$/' => '!j. F Y',
        '/^\d{1,2}\s+[A-Z][a-z]+\s+\d{4}$/' => '!j F Y',
        '/^[A-Z][a-z]+\s+\d{4}$/' => '!F Y',
    ];

    public function parse(string $value): CarbonImmutable
    {
        $normalized = preg_replace_callback(
            '/(?<!\p{L})(Januar|Februar|März|Maerz|April|Mai|Juni|Juli|August|September|Oktober|November|Dezember)(?!\p{L})/iu',
            static fn (array $matches): string => self::GERMAN_MONTHS[mb_strtolower($matches[1], 'UTF-8')],
            trim($value),
        );
        $normalized ??= trim($value);
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        try {
            $timezone = (string) config('maddraxikon.timezone', 'Europe/Berlin');
            $format = null;

            foreach (self::SUPPORTED_FORMATS as $pattern => $candidateFormat) {
                if (preg_match($pattern, $normalized) === 1) {
                    $format = $candidateFormat;

                    break;
                }
            }

            if ($format === null) {
                throw new UnexpectedValueException('Nicht unterstütztes Datumsformat.');
            }

            $date = CarbonImmutable::createFromFormat($format, $normalized, $timezone);
            $errors = CarbonImmutable::getLastErrors();

            if ($date === false || (is_array($errors)
                && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
                || $date->year < 1) {
                throw new UnexpectedValueException('Ungültiges Kalenderdatum.');
            }

            return match ($format) {
                '!Y' => $date->startOfYear(),
                '!Y-m', '!Y-n' => $date->startOfMonth(),
                default => $date->startOfDay(),
            };
        } catch (Throwable $exception) {
            throw new MaddraxikonCrawlException(
                "Veröffentlichungsdatum '{$value}' konnte nicht sicher ausgewertet werden.",
                previous: $exception,
            );
        }
    }
}
