<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\KassenbuchEntryType;
use App\Models\KassenbuchEntry;
use App\Models\Team;
use Carbon\Carbon;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(KassenbuchEntryModelTestFixtures::class);

test('kassenbuch entry can be created', function (): void {
    $user = $this->createMember();
    $team = Team::membersTeam();
    $entry = KassenbuchEntry::create(['team_id' => $team->id, 'created_by' => $user->id, 'buchungsdatum' => '2025-05-01', 'betrag' => 10.5, 'beschreibung' => 'Mitgliedsbeitrag', 'typ' => KassenbuchEntryType::Einnahme->value]);
    $this->assertDatabaseHas('kassenbuch_entries', ['id' => $entry->id, 'team_id' => $team->id, 'created_by' => $user->id, 'buchungsdatum' => '2025-05-01 00:00:00', 'betrag' => 10.5, 'beschreibung' => 'Mitgliedsbeitrag', 'typ' => KassenbuchEntryType::Einnahme->value]);
});

test('mass assignment sets attributes', function (): void {
    $user = $this->createMember();
    $team = Team::membersTeam();
    $entry = KassenbuchEntry::create(['team_id' => $team->id, 'created_by' => $user->id, 'buchungsdatum' => '2025-05-02', 'betrag' => 5, 'beschreibung' => 'Einkauf', 'typ' => KassenbuchEntryType::Ausgabe->value, 'id' => 999]);
    $entry->refresh();
    expect($entry->id)->not->toEqual(999)
        ->and($entry->typ)->toBe(KassenbuchEntryType::Ausgabe)
        ->and($entry->beschreibung)->toEqual('Einkauf');
});

test('kassenbuch entry belongs to team', function (): void {
    $user = $this->createMember();
    $team = Team::membersTeam();
    $entry = KassenbuchEntry::create(['team_id' => $team->id, 'created_by' => $user->id, 'buchungsdatum' => now(), 'betrag' => 12, 'beschreibung' => 'Test', 'typ' => KassenbuchEntryType::Einnahme->value]);
    expect($entry->team->is($team))->toBeTrue();
});

test('kassenbuch entry belongs to creator', function (): void {
    $user = $this->createMember();
    $team = Team::membersTeam();
    $entry = KassenbuchEntry::create(['team_id' => $team->id, 'created_by' => $user->id, 'buchungsdatum' => now(), 'betrag' => 8, 'beschreibung' => 'Test', 'typ' => KassenbuchEntryType::Einnahme->value]);
    expect($entry->creator->is($user))->toBeTrue();
});

test('casts transform attributes', function (): void {
    $user = $this->createMember();
    $team = Team::membersTeam();
    $entry = KassenbuchEntry::create(['team_id' => $team->id, 'created_by' => $user->id, 'buchungsdatum' => '2025-06-01', 'betrag' => '123.45', 'beschreibung' => 'Cast Test', 'typ' => KassenbuchEntryType::Einnahme->value]);
    expect($entry->buchungsdatum)->toBeInstanceOf(Carbon::class)
        ->and($entry->betrag)->toBe('123.45');
});
