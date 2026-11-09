<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(TeamMembersTeamTestFixtures::class);

covers(Team::class);

beforeEach(function (): void {
    Team::clearMembersTeamCache();
});

test('members team returns correct team', function (): void {
    $team = Team::membersTeam();
    expect($team)->not->toBeNull()
        ->and($team->name)->toBe('Mitglieder');
});

test('members team is cached after first call', function (): void {
    DB::flushQueryLog();
    DB::enableQueryLog();
    Team::membersTeam();
    Team::membersTeam();
    $this->assertTeamsQueryCount(1);
});

test('clear members team cache resets cache', function (): void {
    DB::flushQueryLog();
    DB::enableQueryLog();
    Team::membersTeam();
    Team::clearMembersTeamCache();
    Team::membersTeam();
    $this->assertTeamsQueryCount(2);
});

test('members team cache not cleared when description updated', function (): void {
    $team = Team::membersTeam();
    $team->update(['description' => 'updated']);
    DB::flushQueryLog();
    DB::enableQueryLog();
    $cachedTeam = Team::membersTeam();
    $this->assertTeamsQueryCount(0);
    expect($cachedTeam->description)->toBe('updated');
});

test('members team cache cleared when name changes', function (): void {
    $team = Team::membersTeam();
    $team->update(['name' => 'Mitglieder-renamed']);
    DB::flushQueryLog();
    DB::enableQueryLog();
    $renamedTeam = Team::membersTeam();
    $this->assertTeamsQueryCount(1);
    expect($renamedTeam)->toBeNull();
});

test('members team ignores stale cached team id', function (): void {
    $team = Team::membersTeam();
    Team::clearMembersTeamCache();
    cache()->forever(Team::MEMBERS_TEAM_ID_CACHE_KEY, $team->id);
    $team->update(['name' => 'Archiviertes Team']);
    $replacement = Team::factory()->create(['name' => 'Mitglieder']);
    $resolvedTeam = Team::membersTeam();
    expect($resolvedTeam)->not->toBeNull()
        ->and($resolvedTeam->is($replacement))->toBeTrue();
});

test('members team rejects case variant names even with ci collation', function (): void {
    Team::membersTeam()?->delete();
    Team::factory()->create(['name' => 'mitglieder', 'personal_team' => false]);
    Team::clearMembersTeamCache();
    expect(Team::membersTeam())->toBeNull();
});

test('members team returns null when not found', function (): void {
    $team = Team::membersTeam();
    $team->delete();
    DB::flushQueryLog();
    DB::enableQueryLog();
    $firstCall = Team::membersTeam();
    $secondCall = Team::membersTeam();
    $thirdCall = Team::membersTeam();
    expect($firstCall)->toBeNull()
        ->and($secondCall)->toBeNull()
        ->and($thirdCall)->toBeNull();
    $this->assertTeamsQueryCount(3);
});

test('members team cache cleared on team delete', function (): void {
    $team = Team::membersTeam();
    $team->delete();
    Team::factory()->create(['name' => 'Mitglieder']);
    DB::flushQueryLog();
    DB::enableQueryLog();
    $newTeam = Team::membersTeam();
    $this->assertTeamsQueryCount(1);
    expect($newTeam->id)->not->toBe($team->id);
});
