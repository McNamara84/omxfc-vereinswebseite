<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\ReviewBaxxSpecialOffer;
use App\Models\Team;
use App\Models\UserPoint;
use App\Services\ReviewBaxxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CreatesUserWithRole::class, RefreshDatabase::class);

test('effective rule falls back to base rule without active special offer', function (): void {
    $rule = app(ReviewBaxxService::class)->getEffectiveRule();
    expect($rule['is_special_offer'])->toBeFalse()
        ->and($rule['points'])->toBe(1)
        ->and($rule['every_count'])->toBe(10)
        ->and($rule['rule_label'])->toBe('1 Baxx pro 10 Rezensionen');
});

test('active special offer overrides base rule', function (): void {
    ReviewBaxxSpecialOffer::create(['points' => 2, 'every_count' => 1, 'ends_at' => now()->addDay(), 'is_active' => true]);
    $rule = app(ReviewBaxxService::class)->getEffectiveRule();
    expect($rule['is_special_offer'])->toBeTrue()
        ->and($rule['points'])->toBe(2)
        ->and($rule['every_count'])->toBe(1)
        ->and($rule['rule_label'])->toBe('2 Baxx pro Rezension');
});

test('expired special offer is ignored', function (): void {
    ReviewBaxxSpecialOffer::create(['points' => 2, 'every_count' => 1, 'ends_at' => now()->subMinute(), 'is_active' => true]);
    $rule = app(ReviewBaxxService::class)->getEffectiveRule();
    expect($rule['is_special_offer'])->toBeFalse()
        ->and($rule['points'])->toBe(1)
        ->and($rule['every_count'])->toBe(10);
});

test('prominent special offer is only returned for one baxx or more per review', function (): void {
    ReviewBaxxSpecialOffer::create(['points' => 5, 'every_count' => 10, 'ends_at' => now()->addDay(), 'is_active' => true]);
    expect(app(ReviewBaxxService::class)->getProminentSpecialOffer())->toBeNull();
    ReviewBaxxSpecialOffer::query()->delete();
    ReviewBaxxSpecialOffer::create(['points' => 5, 'every_count' => 2, 'ends_at' => now()->addDay(), 'is_active' => true]);
    $banner = app(ReviewBaxxService::class)->getProminentSpecialOffer();
    expect($banner)->not->toBeNull()
        ->and($banner['points_per_review_label'])->toBe('2,5')
        ->and($banner['banner_text'])->toContain('Sonderaktion');
});

test('award points for review uses effective rule interval', function (): void {
    $user = $this->createUserWithRole('Mitglied');
    ReviewBaxxSpecialOffer::create(['points' => 2, 'every_count' => 1, 'ends_at' => now()->addDay(), 'is_active' => true]);
    $awardedPoints = app(ReviewBaxxService::class)->awardPointsForReview($user, 1);
    expect($awardedPoints)->toBe(2);
    $this->assertDatabaseHas('user_points', ['user_id' => $user->id, 'team_id' => $user->currentTeam->id, 'points' => 2]);
    expect(UserPoint::count())->toBe(1);
});

test('award points for review uses explicit team id override', function (): void {
    $user = $this->createUserWithRole('Mitglied');
    $teamId = $user->currentTeam->id;
    $otherTeam = Team::factory()->create();
    $user->forceFill(['current_team_id' => $otherTeam->id])->save();
    $user->unsetRelation('currentTeam');
    $awardedPoints = app(ReviewBaxxService::class)->awardPointsForReview($user, 10, $teamId);
    expect($awardedPoints)->toBe(1);
    $this->assertDatabaseHas('user_points', ['user_id' => $user->id, 'team_id' => $teamId, 'points' => 1]);
});
