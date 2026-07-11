<?php

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('testing')) {
    fwrite(STDERR, sprintf(
        "Refusing to create RPG editor test user outside APP_ENV=testing (current: %s).\n",
        $app->environment(),
    ));
    exit(1);
}

$email = $argv[1] ?? '';

if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php tests/e2e/create-rpg-editor-user.php <email>\n");
    exit(1);
}

$owner = User::query()->firstWhere('email', 'info@maddraxikon.com')
    ?? User::query()->first();

if (! $owner) {
    $owner = User::query()->create([
        'name' => 'Playwright Owner',
        'email' => 'playwright-owner@example.test',
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
        'vorname' => 'Playwright',
        'nachname' => 'Owner',
        'strasse' => 'Teststrasse',
        'hausnummer' => '1',
        'plz' => '12345',
        'stadt' => 'Teststadt',
        'land' => 'Deutschland',
        'telefon' => '0000',
        'verein_gefunden' => 'Sonstiges',
        'mitgliedsbeitrag' => 36.00,
    ]);
}

$membersTeam = Team::membersTeam()
    ?? Team::query()->firstOrCreate(
        ['name' => 'Mitglieder'],
        [
            'user_id' => $owner->id,
            'personal_team' => false,
        ],
    );

$agTeam = Team::query()->firstOrCreate(
    ['name' => 'AG Rollenspiel'],
    [
        'user_id' => $owner->id,
        'personal_team' => false,
    ],
);

$user = User::query()->updateOrCreate(
    ['email' => $email],
    [
        'name' => 'Playwright RPG',
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
        'vorname' => 'Playwright',
        'nachname' => 'RPG',
        'strasse' => 'Teststrasse',
        'hausnummer' => '7',
        'plz' => '12345',
        'stadt' => 'Teststadt',
        'land' => 'Deutschland',
        'telefon' => '0000',
        'verein_gefunden' => 'Sonstiges',
        'mitgliedsbeitrag' => 36.00,
        'current_team_id' => $membersTeam->id,
    ],
);

$membersTeam->users()->syncWithoutDetaching([
    $user->id => ['role' => Role::Mitglied->value],
]);

$agTeam->users()->syncWithoutDetaching([
    $user->id => ['role' => Role::Mitglied->value],
]);

echo $user->email.PHP_EOL;
