<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BaxxEarningRule;
use App\Models\MaddraxiversumBaxxSpecialOffer;
use App\Models\UserPoint;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(MaddraxiversumControllerTestFixtures::class);

beforeEach(function (): void {
    Config::set('app.testing_minimal_layout', true);
});

test('index shows map when user has unlocked reward', function (): void {
    $user = $this->actingMember('Mitglied', 15);
    $this->purchaseMaddraxiversumReward($user);
    $this->actingAs($user);
    $response = $this->get('/maddraxiversum');
    $response->assertOk();
    $response->assertViewIs('maddraxiversum.index');
    $response->assertViewHas('showMap', true);
});

test('index accessible for ehrenmitglied', function (): void {
    $user = $this->actingMember('Ehrenmitglied');
    $this->actingAs($user);
    $this->get('/maddraxiversum')->assertOk();
});

test('index hides map when reward not purchased', function (): void {
    $user = $this->actingMember('Mitglied', 2);
    $this->actingAs($user);
    $response = $this->get('/maddraxiversum');
    $response->assertOk();
    $response->assertViewHas('showMap', false);
    $response->assertSee('Belohnungen einlösen');
});

test('index shows effective maddraxiversum rule when reward is locked', function (): void {
    $user = $this->actingMember('Mitglied', 2);
    $this->actingAs($user);
    MaddraxiversumBaxxSpecialOffer::create(['points' => 4, 'every_count' => 2, 'ends_at' => now()->addDay(), 'is_active' => true]);
    $response = $this->get('/maddraxiversum');
    $response->assertOk();
    $response->assertSee('Aktuelle Vergaberegel für Missionen');
    $response->assertSee('4 Baxx pro 2 Missionen');
    $response->assertSee('Sonderaktion: Jetzt für einen begrenzten Zeitraum 2 Baxx pro Mission!');
});

test('get cities returns api response', function (): void {
    Http::fake(['de.maddraxikon.com/*' => Http::response(['test' => 'data'], 200)]);
    $user = $this->actingMember('Mitglied', 10);
    $this->actingAs($user);
    $response = $this->get('/maddraxikon-staedte');
    $response->assertOk();
    $response->assertJson(['test' => 'data']);
});

test('get mission status returns none when no mission exists', function (): void {
    $user = $this->actingMember();
    $this->actingAs($user);
    $this->getJson('/mission/status')->assertOk()->assertJson(['status' => 'none']);
});

test('get mission status reports progression', function (): void {
    Carbon::setTestNow('2025-01-01 12:00:00');
    $user = $this->actingMember();
    $this->actingAs($user);
    $this->startMission($user);
    Carbon::setTestNow('2025-01-01 12:00:05');
    $this->getJson('/mission/status')->assertOk()->assertJson(['status' => 'traveling', 'current_location' => 'A']);
    Carbon::setTestNow('2025-01-01 12:00:15');
    $this->getJson('/mission/status')->assertOk()->assertJson(['status' => 'traveling', 'current_location' => 'A']);
    Carbon::setTestNow('2025-01-01 12:01:00');
    $this->getJson('/mission/status')->assertOk()->assertJson(['status' => 'traveling', 'current_location' => 'A']);
});

test('check mission status marks completed and awards points from effective rule', function (): void {
    Carbon::setTestNow('2025-01-01 12:00:00');
    $user = $this->actingMember();
    $this->actingAs($user);
    BaxxEarningRule::updateOrCreate(['action_key' => 'maddraxiversum_mission'], ['label' => 'Maddraxiversum-Mission', 'description' => 'Testregel', 'points' => 7, 'every_count' => 1, 'is_active' => true]);
    $this->startMission($user);
    Carbon::setTestNow('2025-01-01 12:00:45');
    $this->postJson('/mission/status-pruefen')->assertOk()->assertJson(['status' => 'completed']);
    $this->assertDatabaseHas('missions', ['user_id' => $user->id, 'completed' => true]);
    $this->assertDatabaseHas('user_points', ['user_id' => $user->id, 'team_id' => $user->currentTeam->id, 'points' => 7]);
});

test('check mission status uses active special offer rule', function (): void {
    Carbon::setTestNow('2025-01-01 12:00:00');
    $user = $this->actingMember();
    $this->actingAs($user);
    MaddraxiversumBaxxSpecialOffer::create(['points' => 11, 'every_count' => 1, 'ends_at' => now()->addDay(), 'is_active' => true]);
    $this->startMission($user);
    Carbon::setTestNow('2025-01-01 12:00:45');
    $this->postJson('/mission/status-pruefen')->assertOk()->assertJson(['status' => 'completed']);
    $this->assertDatabaseHas('user_points', ['user_id' => $user->id, 'team_id' => $user->currentTeam->id, 'points' => 11]);
    expect(UserPoint::count())->toBe(1);
});
