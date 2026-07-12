<?php

namespace Tests\Unit;

use App\Models\MaddraxiversumBaxxSpecialOffer;
use App\Models\Mission;
use App\Models\UserPoint;
use App\Services\MaddraxiversumBaxxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class MaddraxiversumBaxxServiceTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    public function test_effective_rule_falls_back_to_base_rule_without_active_special_offer(): void
    {
        $rule = app(MaddraxiversumBaxxService::class)->getEffectiveRule();

        $this->assertFalse($rule['is_special_offer']);
        $this->assertSame(5, $rule['points']);
        $this->assertSame(1, $rule['every_count']);
        $this->assertSame('5 Baxx pro Mission', $rule['rule_label']);
    }

    public function test_active_special_offer_overrides_base_rule(): void
    {
        MaddraxiversumBaxxSpecialOffer::create([
            'points' => 2,
            'every_count' => 1,
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ]);

        $rule = app(MaddraxiversumBaxxService::class)->getEffectiveRule();

        $this->assertTrue($rule['is_special_offer']);
        $this->assertSame(2, $rule['points']);
        $this->assertSame(1, $rule['every_count']);
        $this->assertSame('2 Baxx pro Mission', $rule['rule_label']);
    }

    public function test_expired_special_offer_is_ignored(): void
    {
        MaddraxiversumBaxxSpecialOffer::create([
            'points' => 2,
            'every_count' => 1,
            'ends_at' => now()->subMinute(),
            'is_active' => true,
        ]);

        $rule = app(MaddraxiversumBaxxService::class)->getEffectiveRule();

        $this->assertFalse($rule['is_special_offer']);
        $this->assertSame(5, $rule['points']);
        $this->assertSame(1, $rule['every_count']);
    }

    public function test_prominent_special_offer_is_only_returned_for_one_baxx_or_more_per_mission(): void
    {
        MaddraxiversumBaxxSpecialOffer::create([
            'points' => 1,
            'every_count' => 4,
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ]);

        $this->assertNull(app(MaddraxiversumBaxxService::class)->getProminentSpecialOffer());

        MaddraxiversumBaxxSpecialOffer::query()->delete();

        MaddraxiversumBaxxSpecialOffer::create([
            'points' => 5,
            'every_count' => 2,
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ]);

        $banner = app(MaddraxiversumBaxxService::class)->getProminentSpecialOffer();

        $this->assertNotNull($banner);
        $this->assertSame('2,5', $banner['points_per_mission_label']);
        $this->assertStringContainsString('Sonderaktion', $banner['banner_text']);
    }

    public function test_explicit_mission_reward_has_priority_over_special_offer_and_base_rule(): void
    {
        MaddraxiversumBaxxSpecialOffer::create([
            'points' => 3,
            'every_count' => 1,
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ]);

        $mission = new Mission;
        $mission->setAttribute('reward', 9);

        $points = app(MaddraxiversumBaxxService::class)->resolveMissionRewardPoints($mission);

        $this->assertSame(9, $points);
    }

    public function test_award_points_for_mission_uses_effective_rule_and_current_team(): void
    {
        $user = $this->createUserWithRole('Mitglied');

        MaddraxiversumBaxxSpecialOffer::create([
            'points' => 4,
            'every_count' => 1,
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ]);

        $awardedPoints = app(MaddraxiversumBaxxService::class)->awardPointsForMission($user, completedMissionCount: 1);

        $this->assertSame(4, $awardedPoints);
        $this->assertDatabaseHas('user_points', [
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'points' => 4,
        ]);
        $this->assertSame(1, UserPoint::count());
    }

    public function test_award_points_for_mission_returns_zero_without_current_team(): void
    {
        $user = $this->createUserWithRole('Mitglied');
        $user->setRelation('currentTeam', null);

        $awardedPoints = app(MaddraxiversumBaxxService::class)->awardPointsForMission($user, completedMissionCount: 1);

        $this->assertSame(0, $awardedPoints);
        $this->assertSame(0, UserPoint::count());
    }

    public function test_award_points_for_mission_respects_every_count_for_effective_rule(): void
    {
        $user = $this->createUserWithRole('Mitglied');

        MaddraxiversumBaxxSpecialOffer::create([
            'points' => 6,
            'every_count' => 2,
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ]);

        $firstAward = app(MaddraxiversumBaxxService::class)->awardPointsForMission($user, completedMissionCount: 1);
        $secondAward = app(MaddraxiversumBaxxService::class)->awardPointsForMission($user, completedMissionCount: 2);

        $this->assertSame(0, $firstAward);
        $this->assertSame(6, $secondAward);
        $this->assertDatabaseHas('user_points', [
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'points' => 6,
        ]);
        $this->assertSame(1, UserPoint::count());
    }
}
