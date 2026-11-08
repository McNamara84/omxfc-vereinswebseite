<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Tours\TourDefinition;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

pest()->extend(TestCase::class);

covers(TourDefinition::class);

test('start mode defaults to automatic when omitted', function (): void {
    expect(TourDefinition::fromArray('hauptmenue', [])->startMode)->toBe('automatic');
});

test('supported start modes are preserved', function (string $startMode): void {
    $definition = TourDefinition::fromArray('profilpflege', ['start_mode' => $startMode]);
    expect($definition->startMode)->toBe($startMode);
})->with([['automatic'], ['invitation']]);

test('invalid start modes are rejected', function (mixed $startMode): void {
    expect(fn () => TourDefinition::fromArray('profilpflege', ['start_mode' => $startMode]))->toThrow(InvalidArgumentException::class, 'Ungültiger start_mode für Tour [profilpflege]. Erlaubt sind automatic und invitation.');
})->with([['invitaton'], [''], ['Invitation'], [' automatic'], [null], [false], [true], [1], [[]]]);
