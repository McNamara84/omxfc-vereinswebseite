<?php

namespace App\Support;

use InvalidArgumentException;

class Euro
{
    public static function toCents(string|int|float $amount): int
    {
        if (is_int($amount) || is_float($amount)) {
            return (int) round($amount * 100);
        }

        $normalized = trim(str_replace(['€', ' '], '', $amount));

        if ($normalized === '') {
            throw new InvalidArgumentException('Der Betrag darf nicht leer sein.');
        }

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        if (! is_numeric($normalized)) {
            throw new InvalidArgumentException('Der Betrag muss eine gueltige Euro-Zahl sein.');
        }

        return (int) round(((float) $normalized) * 100);
    }

    public static function format(int $amountInCents): string
    {
        return number_format($amountInCents / 100, 2, ',', '.').' €';
    }

    public static function decimal(int $amountInCents): string
    {
        return number_format($amountInCents / 100, 2, '.', '');
    }
}
