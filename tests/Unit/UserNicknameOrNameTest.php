<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

pest()->extend(TestCase::class);

covers(User::class);

test('display names follow the nickname, name, civil name and placeholder priority', function (User $user, string $expected): void {
    expect($user->nicknameOrName())->toBe($expected);
})->with([
    'it prefers a trimmed nickname' => [fn (): User => new User(['name' => 'Max Mustermann', 'alias' => '  Maxi  ']), 'Maxi'],
    'it falls back to the name without a nickname' => [fn (): User => new User(['name' => 'Max Mustermann', 'alias' => null]), 'Max Mustermann'],
    'it falls back to the name for a blank nickname' => [fn (): User => new User(['name' => '  Max Mustermann  ', 'alias' => '   ']), 'Max Mustermann'],
    'it falls back to the civil name when name is blank' => [fn (): User => new User(['name' => '   ', 'alias' => '   ', 'vorname' => '  Max  ', 'nachname' => '  Mustermann  ']), 'Max Mustermann'],
    'it returns a safe placeholder when all names are blank' => [fn (): User => new User(['name' => '   ', 'alias' => null, 'vorname' => '   ', 'nachname' => null]), User::UNKNOWN_DISPLAY_NAME],
]);
