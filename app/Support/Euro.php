<?php

namespace App\Support;

use InvalidArgumentException;

class Euro
{
    private const VALIDATION_PATTERN = '/^\d+(?:[.,]\d{1,2})?$/';

    public const VALIDATION_RULE = 'regex:'.self::VALIDATION_PATTERN;

    public static function toCents(string|int|float $amount): int
    {
        $normalized = self::normalize($amount);

        if ($normalized === '') {
            throw new InvalidArgumentException('Der Betrag darf nicht leer sein.');
        }

        if (! preg_match(self::validationPattern(), $normalized)) {
            throw new InvalidArgumentException('Der Betrag muss eine gültige Euro-Zahl sein.');
        }

        $normalized = str_replace(',', '.', $normalized);
        // The grammar permits at most one point; splitting/padding to three preserves both assigned values.
        /* @pest-mutate-ignore: IncrementInteger */
        [$euros, $cents] = array_pad(explode('.', $normalized, 2), 2, '0');

        // Arithmetic already coerces both validated digit strings to integers.
        /* @pest-mutate-ignore: RemoveIntegerCast */
        return ((int) $euros * 100) + (int) str_pad($cents, 2, '0');
    }

    public static function validationPattern(): string
    {
        return self::VALIDATION_PATTERN;
    }

    public static function format(int $amountInCents): string
    {
        return number_format($amountInCents / 100, 2, ',', '.').' €';
    }

    public static function decimal(int $amountInCents): string
    {
        return number_format($amountInCents / 100, 2, '.', '');
    }

    private static function normalize(string|int|float $amount): string
    {
        // Falling through to trim coerces integers identically in this weakly typed file.
        /* @pest-mutate-ignore: RemoveEarlyReturn */
        if (is_int($amount)) {
            // Weak return coercion and trim's fallback produce the same string; recheck if strict_types changes.
            /* @pest-mutate-ignore: RemoveStringCast */
            return (string) $amount;
        }

        if (is_float($amount)) {
            return number_format($amount, 2, '.', '');
        }

        return trim($amount);
    }
}
