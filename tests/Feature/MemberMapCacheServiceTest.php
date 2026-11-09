<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\MemberMapCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('cached map data is retrieved with one cache read', function (): void {
    $team = Team::factory()->create();
    $cachedData = ['memberData' => [], 'centerLat' => 51.1657, 'centerLon' => 10.4515];
    Cache::shouldReceive('get')->once()->with("member_map_data_v2_team_{$team->id}")->andReturn($cachedData);
    Cache::shouldReceive('has')->never();
    expect((new MemberMapCacheService)->getMemberMapData($team))->toBe($cachedData);
});

test('service caches and refreshes member map data', function (): void {
    Cache::flush();
    $team = Team::factory()->create();
    $team->users()->detach();
    $user1 = User::factory()->create(['plz' => '11111', 'stadt' => 'City1', 'lat' => 10.0, 'lon' => 20.0, 'current_team_id' => $team->id]);
    $user2 = User::factory()->create(['plz' => '22222', 'stadt' => 'City2', 'lat' => 20.0, 'lon' => 30.0, 'current_team_id' => $team->id]);
    $team->users()->attach($user1, ['role' => Role::Mitglied->value]);
    $team->users()->attach($user2, ['role' => Role::Mitglied->value]);
    $service = new MemberMapCacheService;
    $data = $service->getMemberMapData($team);
    expect($data['memberData'])->toHaveCount(2)
        ->and($data['centerLat'])->toEqual(15.0)
        ->and($data['centerLon'])->toEqual(25.0);
    $user1->update(['lat' => 30.0, 'lon' => 40.0]);
    $cached = $service->getMemberMapData($team);
    expect($cached['centerLat'])->toEqual(15.0)
        ->and($cached['centerLon'])->toEqual(25.0);
    $refreshed = $service->refresh($team);
    expect($refreshed['centerLat'])->toEqual(25.0)
        ->and($refreshed['centerLon'])->toEqual(35.0);
});

test('member without coordinates is geocoded and included', function (): void {
    Cache::flush();
    $team = Team::factory()->create();
    $team->users()->detach();
    $user = User::factory()->create(['plz' => '12345', 'stadt' => 'City', 'lat' => null, 'lon' => null, 'current_team_id' => $team->id]);
    $team->users()->attach($user, ['role' => Role::Mitglied->value]);
    $service = new MemberMapCacheService;
    $data = $service->getMemberMapData($team);
    expect($data['memberData'])->toHaveCount(1)
        ->and($data['centerLat'])->toEqual((float) self::DEFAULT_LAT)
        ->and($data['centerLon'])->toEqual((float) self::DEFAULT_LON);
});

test('map data uses nickname with name fallback', function (): void {
    Cache::flush();
    $team = Team::factory()->create();
    $team->users()->detach();
    $withNickname = User::factory()->create(['name' => 'Klara Klarname', 'alias' => 'Kiki', 'plz' => '11111', 'stadt' => 'Nickname City', 'lat' => 10.0, 'lon' => 20.0, 'current_team_id' => $team->id]);
    $withoutNickname = User::factory()->create(['name' => 'Name Ohne Alias', 'alias' => null, 'plz' => '22222', 'stadt' => 'Fallback City', 'lat' => 20.0, 'lon' => 30.0, 'current_team_id' => $team->id]);
    $withCivilNameFallback = User::factory()->create(['name' => '   ', 'alias' => '   ', 'vorname' => 'Civil', 'nachname' => 'Fallback', 'plz' => '33333', 'stadt' => 'Civil City', 'lat' => 30.0, 'lon' => 40.0, 'current_team_id' => $team->id]);
    $team->users()->attach($withNickname, ['role' => Role::Mitglied->value]);
    $team->users()->attach($withoutNickname, ['role' => Role::Mitglied->value]);
    $team->users()->attach($withCivilNameFallback, ['role' => Role::Mitglied->value]);
    $data = (new MemberMapCacheService)->getMemberMapData($team);
    $names = array_column($data['memberData'], 'name');
    expect($names)->toContain('Kiki')
        ->toContain('Name Ohne Alias')
        ->toContain('Civil Fallback')->not->toContain('Klara Klarname');
});

test('members without plz or with anwaerter role are excluded', function (): void {
    Cache::flush();
    $team = Team::factory()->create();
    $team->users()->detach();
    $valid = User::factory()->create(['plz' => '12345', 'stadt' => 'Valid City', 'lat' => 10.0, 'lon' => 20.0, 'current_team_id' => $team->id]);
    $anwaerter = User::factory()->create(['plz' => '54321', 'stadt' => 'Skip City', 'lat' => 30.0, 'lon' => 40.0, 'current_team_id' => $team->id]);
    $noPlz = User::factory()->create(['plz' => '', 'stadt' => 'NoPlz City', 'lat' => 50.0, 'lon' => 60.0, 'current_team_id' => $team->id]);
    $team->users()->attach($valid, ['role' => Role::Mitglied->value]);
    $team->users()->attach($anwaerter, ['role' => Role::Anwaerter->value]);
    $team->users()->attach($noPlz, ['role' => Role::Mitglied->value]);
    $service = new MemberMapCacheService;
    $data = $service->getMemberMapData($team);
    expect($data['memberData'])->toHaveCount(1);
    $member = $data['memberData'][0];
    expect($member['city'])->toEqual('Valid City')
        ->and($data['centerLat'])->toEqual(10.0)
        ->and($data['centerLon'])->toEqual(20.0);
});

test('jitter is applied deterministically', function (): void {
    Cache::flush();
    $team = Team::factory()->create();
    $team->users()->detach();
    $user = User::factory()->create(['plz' => '11111', 'stadt' => 'Jitter City', 'lat' => 10.0, 'lon' => 20.0, 'current_team_id' => $team->id]);
    $team->users()->attach($user, ['role' => Role::Mitglied->value]);
    $service = new MemberMapCacheService;
    mt_srand(1234);
    $data = $service->getMemberMapData($team);
    mt_srand(1234);
    $expectedLat = 10.0 + mt_rand(-50, 50) / 10000;
    $expectedLon = 20.0 + mt_rand(-50, 50) / 10000;
    expect($data['memberData'][0]['lat'])->toEqualWithDelta($expectedLat, 1.0E-6)
        ->and($data['memberData'][0]['lon'])->toEqualWithDelta($expectedLon, 1.0E-6);
});

test('refresh generates new jitter', function (): void {
    Cache::flush();
    $team = Team::factory()->create();
    $team->users()->detach();
    $user = User::factory()->create(['plz' => '22222', 'stadt' => 'Refresh City', 'lat' => 10.0, 'lon' => 20.0, 'current_team_id' => $team->id]);
    $team->users()->attach($user, ['role' => Role::Mitglied->value]);
    $service = new MemberMapCacheService;
    mt_srand(1);
    $first = $service->getMemberMapData($team);
    $firstLat = $first['memberData'][0]['lat'];
    $firstLon = $first['memberData'][0]['lon'];
    mt_srand(2);
    $second = $service->refresh($team);
    $secondLat = $second['memberData'][0]['lat'];
    $secondLon = $second['memberData'][0]['lon'];
    expect($secondLat)->not->toEqual($firstLat)
        ->and($secondLon)->not->toEqual($firstLon);
});
