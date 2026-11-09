<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

pest()->extend(TestCase::class);

covers(User::class);

test('public first name prefers vorname', function (): void {
    $user = new User(['name' => 'Martin Gobrecht', 'vorname' => 'Martin']);
    expect($user->publicFirstName())->toBe('Martin');
});

test('public first name falls back to first name token', function (): void {
    $user = new User(['name' => 'Leitung Test', 'vorname' => null]);
    expect($user->publicFirstName())->toBe('Leitung');
});

test('public first name returns null when no name is available', function (): void {
    $user = new User(['name' => '   ', 'vorname' => null]);
    expect($user->publicFirstName())->toBeNull();
});
