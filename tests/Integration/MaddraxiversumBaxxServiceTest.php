<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\MaddraxiversumBaxxSpecialOffer;
use App\Models\Mission;
use App\Models\UserPoint;
use App\Services\MaddraxiversumBaxxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CreatesUserWithRole::class, RefreshDatabase::class);

test('effective rule falls back to base rule without active special offer', function (): void {
    $rule = app(MaddraxiversumBaxxService::class)->getEffectiveRule();
    expect($rule['is_special_offer'])->toBeFalse()
        ->and($rule['points'])->toBe(5)
        ->and($rule['every_count'])->toBe(1)
        ->and($rule['rule_label'])->toBe('5 Baxx pro Mission');
});

test('active special offer overrides base rule', function (): void {
    MaddraxiversumBaxxSpecialOffer::create(['points' => 2, 'every_count' => 1, 'ends_at' => now()->addDay(), 'is_active' => true]);
    $rule = app(MaddraxiversumBaxxService::class)->getEffectiveRule();
    expect($rule['is_special_offer'])->toBeTrue()
        ->and($rule['points'])->toBe(2)
        ->and($rule['every_count'])->toBe(1)
        ->and($rule['rule_label'])->toBe('2 Baxx pro Mission');
});

test('expired special offer is ignored', function (): void {
    MaddraxiversumBaxxSpecialOffer::create(['points' => 2, 'every_count' => 1, 'ends_at' => now()->subMinute(), 'is_active' => true]);
    $rule = app(MaddraxiversumBaxxService::class)->getEffectiveRule();
    expect($rule['is_special_offer'])->toBeFalse()
        ->and($rule['points'])->toBe(5)
        ->and($rule['every_count'])->toBe(1);
});

test('prominent special offer is only returned for one baxx or more per mission', function (): void {
    MaddraxiversumBaxxSpecialOffer::create(['points' => 1, 'every_count' => 4, 'ends_at' => now()->addDay(), 'is_active' => true]);
    expect(app(MaddraxiversumBaxxService::class)->getProminentSpecialOffer())->toBeNull();
    MaddraxiversumBaxxSpecialOffer::query()->delete();
    MaddraxiversumBaxxSpecialOffer::create(['points' => 5, 'every_count' => 2, 'ends_at' => now()->addDay(), 'is_active' => true]);
    $banner = app(MaddraxiversumBaxxService::class)->getProminentSpecialOffer();
    expect($banner)->not->toBeNull()
        ->and($banner['points_per_mission_label'])->toBe('2,5')
        ->and($banner['banner_text'])->toContain('Sonderaktion');
});

test('explicit mission reward has priority over special offer and base rule', function (): void {
    MaddraxiversumBaxxSpecialOffer::create(['points' => 3, 'every_count' => 1, 'ends_at' => now()->addDay(), 'is_active' => true]);
    $mission = new Mission;
    $mission->setAttribute('reward', 9);
    $points = app(MaddraxiversumBaxxService::class)->resolveMissionRewardPoints($mission);
    expect($points)->toBe(9);
});

test('award points for mission uses effective rule and current team', function (): void {
    $user = $this->createUserWithRole('Mitglied');
    MaddraxiversumBaxxSpecialOffer::create(['points' => 4, 'every_count' => 1, 'ends_at' => now()->addDay(), 'is_active' => true]);
    $awardedPoints = app(MaddraxiversumBaxxService::class)->awardPointsForMission($user, completedMissionCount: 1);
    expect($awardedPoints)->toBe(4);
    $this->assertDatabaseHas('user_points', ['user_id' => $user->id, 'team_id' => $user->currentTeam->id, 'points' => 4]);
    expect(UserPoint::count())->toBe(1);
});

test('award points for mission returns zero without current team', function (): void {
    $user = $this->createUserWithRole('Mitglied');
    $user->setRelation('currentTeam', null);
    $awardedPoints = app(MaddraxiversumBaxxService::class)->awardPointsForMission($user, completedMissionCount: 1);
    expect($awardedPoints)->toBe(0)
        ->and(UserPoint::count())->toBe(0);
});

test('award points for mission respects every count for effective rule', function (): void {
    $user = $this->createUserWithRole('Mitglied');
    MaddraxiversumBaxxSpecialOffer::create(['points' => 6, 'every_count' => 2, 'ends_at' => now()->addDay(), 'is_active' => true]);
    $firstAward = app(MaddraxiversumBaxxService::class)->awardPointsForMission($user, completedMissionCount: 1);
    $secondAward = app(MaddraxiversumBaxxService::class)->awardPointsForMission($user, completedMissionCount: 2);
    expect($firstAward)->toBe(0)
        ->and($secondAward)->toBe(6);
    $this->assertDatabaseHas('user_points', ['user_id' => $user->id, 'team_id' => $user->currentTeam->id, 'points' => 6]);
    expect(UserPoint::count())->toBe(1);
});
