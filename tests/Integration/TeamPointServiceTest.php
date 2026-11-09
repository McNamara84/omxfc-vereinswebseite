<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Enums\Role;
use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamPointService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(TeamPointServiceTestFixtures::class);

covers(TeamPointService::class);

beforeEach(function (): void {
    $this->service = new TeamPointService;
    $this->team = Team::membersTeam();
    $this->team->users()->detach();
    $this->team->refresh();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

test('get user points returns team points', function (): void {
    $user = $this->memberWithPoints(7);
    expect($this->service->getUserPoints($user))->toBe(7);
});

test('get user points for team ignores current team selection', function (): void {
    $user = $this->memberWithPoints();
    $this->addPoints($user, 9, Carbon::now());
    $otherTeam = Team::factory()->create(['personal_team' => false]);
    $otherTeam->users()->attach($user, ['role' => Role::Mitglied->value]);
    $userWithOtherCurrentTeam = $user->fresh();
    $userWithOtherCurrentTeam->current_team_id = $otherTeam->id;
    $userWithOtherCurrentTeam->unsetRelation('currentTeam');
    expect($this->service->getUserPointsForTeam($userWithOtherCurrentTeam, $this->team))->toBe(9);
});

test('get user point trend returns last seven days', function (): void {
    Carbon::setTestNow(Carbon::create(2024, 1, 10, 12));
    $user = $this->memberWithPoints();
    $this->addPoints($user, 5, Carbon::now()->subDays(1));
    $this->addPoints($user, 3, Carbon::now()->subDays(3));
    $trend = $this->service->getUserPointTrend($user, $this->team);
    expect($trend)->toHaveCount(7);
    $trendByDate = collect($trend)->keyBy('date');
    expect($trendByDate[Carbon::now()->subDays(1)->toDateString()]['points'])->toBe(5)
        ->and($trendByDate[Carbon::now()->subDays(3)->toDateString()]['points'])->toBe(3)
        ->and($trendByDate[Carbon::now()->subDays(2)->toDateString()]['points'])->toBe(0);
});

test('dashboard metrics calculates team average and progress', function (): void {
    Carbon::setTestNow(Carbon::create(2024, 1, 10, 12));
    $user = $this->memberWithPoints();
    $otherOne = $this->memberWithPoints();
    $otherTwo = $this->memberWithPoints();
    $this->addPoints($user, 12, Carbon::now());
    $this->addPoints($otherOne, 18, Carbon::now());
    // $otherTwo intentionally keeps zero points to check averaging with non-contributors.
    $metrics = $this->service->getDashboardMetrics($user, $this->team);
    expect($metrics['team_average'])->toBe(10.0)
        ->and($metrics['team_average_progress'])->toBe(100)
        ->and($metrics['weekly']['total'])->toBe(12)
        ->and($metrics['weekly']['target'])->toBe(9)
        ->and($metrics['weekly']['progress'])->toBe(100);
});

test('dashboard metrics uses provided team instead of current team', function (): void {
    Carbon::setTestNow(Carbon::create(2024, 1, 10, 12));
    $user = $this->memberWithPoints();
    $this->addPoints($user, 12, Carbon::now());
    $otherTeam = Team::factory()->create(['personal_team' => false]);
    $otherTeam->users()->attach($user, ['role' => Role::Mitglied->value]);
    User::findOrFail($user->id)->points()->create(['team_id' => $otherTeam->id, 'points' => 99]);
    $userWithOtherCurrentTeam = $user->fresh();
    $userWithOtherCurrentTeam->current_team_id = $otherTeam->id;
    $userWithOtherCurrentTeam->unsetRelation('currentTeam');
    $metrics = $this->service->getDashboardMetrics($userWithOtherCurrentTeam, $this->team);
    expect($metrics['user_points'])->toBe(12);
});

test('dashboard metrics exposes leaderboard and rank gap', function (): void {
    Carbon::setTestNow(Carbon::create(2024, 1, 10, 12));
    $user = $this->memberWithPoints();
    $leader = $this->memberWithPoints();
    $second = $this->memberWithPoints();
    $third = $this->memberWithPoints();
    $this->addPoints($leader, 60, Carbon::now());
    $this->addPoints($second, 40, Carbon::now());
    $this->addPoints($third, 20, Carbon::now());
    $this->addPoints($user, 5, Carbon::now());
    $metrics = $this->service->getDashboardMetrics($user, $this->team);
    expect($metrics['user_rank'])->toBe(4)
        ->and($metrics['points_to_next_rank'])->toBe(15)
        ->and($metrics['next_rank_points'])->toBe(20);
    $highlight = collect($metrics['leaderboard'])->firstWhere('is_current_user');
    expect($highlight)->not->toBeNull()
        ->and($highlight['name'])->toBe($user->name)
        ->and($highlight['is_additional'] ?? false)->toBeFalse()
        ->and($highlight['points'])->toBe(5)
        ->and($highlight['rank'])->toBe(4);
});

test('dashboard metrics appends user when outside leaderboard limit', function (): void {
    Carbon::setTestNow(Carbon::create(2024, 1, 10, 12));
    $user = $this->memberWithPoints();
    foreach ([60, 55, 50, 45, 40, 35] as $points) {
        $competitor = $this->memberWithPoints();
        $this->addPoints($competitor, $points, Carbon::now());
    }
    $this->addPoints($user, 5, Carbon::now());
    $metrics = $this->service->getDashboardMetrics($user, $this->team);
    expect($metrics['user_rank'])->toBe(7);
    $highlight = collect($metrics['leaderboard'])->firstWhere('is_current_user');
    expect($highlight)->not->toBeNull()
        ->and($highlight['is_additional'] ?? false)->toBeTrue()
        ->and($highlight['rank'])->toBe(7)
        ->and($highlight['points'])->toBe(5);
});

test('weekly target uses peer average from last seven days', function (): void {
    Carbon::setTestNow(Carbon::create(2024, 1, 17, 8));
    $user = $this->memberWithPoints();
    $peerWithPoints = $this->memberWithPoints();
    $peerWithoutPoints = $this->memberWithPoints();
    $this->addPoints($user, 4, Carbon::now());
    $this->addPoints($peerWithPoints, 10, Carbon::now()->subDays(2));
    // $peerWithoutPoints intentionally collects no points this week to be part of the average.
    $metrics = $this->service->getDashboardMetrics($user, $this->team);
    expect($metrics['weekly']['target'])->toBe(5)
        ->and($metrics['weekly']['progress'])->toBe(80);
});

test('assert reward unlocked passes when purchased', function (): void {
    $user = $this->memberWithPoints(0);
    $this->actingAs($user);
    $reward = Reward::factory()->create(['slug' => 'test-unlock', 'cost_baxx' => 5]);
    RewardPurchase::factory()->create(['user_id' => $user->id, 'reward_id' => $reward->id, 'cost_baxx' => 5]);
    $this->service->assertRewardUnlocked('test-unlock');
    expect(true)->toBeTrue();
    // No exception thrown
});

test('assert reward unlocked throws when not purchased', function (): void {
    $user = $this->memberWithPoints(0);
    $this->actingAs($user);
    Reward::factory()->create(['slug' => 'locked-feature', 'cost_baxx' => 5]);
    expect(fn () => $this->service->assertRewardUnlocked('locked-feature'))->toThrow(AuthorizationException::class);
});
