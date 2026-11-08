<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Euro;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

pest()->extend(TestCase::class);

covers(Euro::class);
mutates(Euro::class);

test('to cents accepts plain and decimal amounts', function (string|int|float $amount, int $cents): void {
    expect(Euro::toCents($amount))->toBe($cents);
})->with([
    'whole string' => ['12', 1200],
    'decimal point' => ['12.34', 1234],
    'decimal comma' => ['12,34', 1234],
    'integer' => [12, 1200],
    'rounded float' => [12.345, 1235],
    'single decimal place' => ['12.3', 1230],
    'one cent' => ['0.01', 1],
    'comma tenths' => ['0,1', 10],
    'whitespace' => [' 12,34 ', 1234],
    'zero' => [0, 0],
    'leading zero' => ['0012.34', 1234],
    'float tenths' => [0.1, 10],
    'float without thousands separator' => [1234.56, 123456],
]);

test('to cents rejects invalid money formats', function (string|int $amount): void {
    expect(fn () => Euro::toCents($amount))
        ->toThrow(InvalidArgumentException::class, 'Der Betrag muss eine gültige Euro-Zahl sein.');
})->with([
    'scientific notation' => ['1e3'],
    'too many point decimals' => ['12.345'],
    'too many comma decimals' => ['12,345'],
    'embedded space' => ['12 34'],
    'negative integer' => [-1],
    'negative string' => ['-12'],
    'missing fractional digits' => ['12.'],
    'missing integer digits' => ['.12'],
    'mixed separators' => ['1,234.56'],
    'currency suffix' => ['12 €'],
    'currency prefix' => ['€ 12'],
    'letters' => ['twelve'],
]);

test('empty amounts report the specific validation error', function (string $amount): void {
    expect(fn () => Euro::toCents($amount))
        ->toThrow(InvalidArgumentException::class, 'Der Betrag darf nicht leer sein.');
})->with(['empty' => [''], 'whitespace' => [" \t\n"]]);

test('cent amounts retain their public display and decimal formats', function (int $cents, string $formatted, string $decimal): void {
    expect(Euro::format($cents))->toBe($formatted)
        ->and(Euro::decimal($cents))->toBe($decimal);
})->with([
    'zero' => [0, '0,00 €', '0.00'],
    'one cent' => [1, '0,01 €', '0.01'],
    'thousands' => [123456, '1.234,56 €', '1234.56'],
    'negative accounting amount' => [-123, '-1,23 €', '-1.23'],
]);
