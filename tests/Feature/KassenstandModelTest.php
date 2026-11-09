<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Kassenstand;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(KassenstandModelTestFixtures::class);

test('kassenstand can be created', function (): void {
    $team = $this->getTeam();
    $kassenstand = Kassenstand::create(['team_id' => $team->id, 'betrag' => 10.5, 'letzte_aktualisierung' => '2025-01-01']);
    $this->assertDatabaseHas('kassenstand', ['id' => $kassenstand->id, 'team_id' => $team->id, 'betrag' => 10.5, 'letzte_aktualisierung' => '2025-01-01 00:00:00']);
});

test('kassenstand belongs to team', function (): void {
    $team = $this->getTeam();
    $kassenstand = Kassenstand::create(['team_id' => $team->id, 'betrag' => 5.0, 'letzte_aktualisierung' => now()]);
    expect($kassenstand->team->is($team))->toBeTrue();
});

test('attributes are cast correctly', function (): void {
    $team = $this->getTeam();
    $kassenstand = Kassenstand::create(['team_id' => $team->id, 'betrag' => '4.55', 'letzte_aktualisierung' => '2025-02-15']);
    $kassenstand->refresh();
    expect($kassenstand->betrag)->toBe('4.55')
        ->and($kassenstand->letzte_aktualisierung->format('Y-m-d'))->toEqual('2025-02-15');
});

test('kassenstand can be updated', function (): void {
    $team = $this->getTeam();
    $kassenstand = Kassenstand::create(['team_id' => $team->id, 'betrag' => 0, 'letzte_aktualisierung' => now()]);
    $kassenstand->update(['betrag' => 20.25, 'letzte_aktualisierung' => '2025-02-01']);
    $kassenstand->refresh();
    expect($kassenstand->betrag)->toBe('20.25')
        ->and($kassenstand->letzte_aktualisierung->format('Y-m-d'))->toEqual('2025-02-01');
});
