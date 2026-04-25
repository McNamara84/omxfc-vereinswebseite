<?php

namespace Tests\Unit;

use App\Models\ReviewBaxxSpecialOffer;
use App\Models\UserPoint;
use App\Services\ReviewBaxxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class ReviewBaxxServiceTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    public function test_effective_rule_falls_back_to_base_rule_without_active_special_offer(): void
    {
        $rule = app(ReviewBaxxService::class)->getEffectiveRule();

        $this->assertFalse($rule['is_special_offer']);
        $this->assertSame(1, $rule['points']);
        $this->assertSame(10, $rule['every_count']);
        $this->assertSame('1 Baxx pro 10 Rezensionen', $rule['rule_label']);
    }

    public function test_active_special_offer_overrides_base_rule(): void
    {
        ReviewBaxxSpecialOffer::create([
            'points' => 2,
            'every_count' => 1,
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ]);

        $rule = app(ReviewBaxxService::class)->getEffectiveRule();

        $this->assertTrue($rule['is_special_offer']);
        $this->assertSame(2, $rule['points']);
        $this->assertSame(1, $rule['every_count']);
        $this->assertSame('2 Baxx pro Rezension', $rule['rule_label']);
    }

    public function test_expired_special_offer_is_ignored(): void
    {
        ReviewBaxxSpecialOffer::create([
            'points' => 2,
            'every_count' => 1,
            'ends_at' => now()->subMinute(),
            'is_active' => true,
        ]);

        $rule = app(ReviewBaxxService::class)->getEffectiveRule();

        $this->assertFalse($rule['is_special_offer']);
        $this->assertSame(1, $rule['points']);
        $this->assertSame(10, $rule['every_count']);
    }

    public function test_prominent_special_offer_is_only_returned_for_one_baxx_or_more_per_review(): void
    {
        ReviewBaxxSpecialOffer::create([
            'points' => 5,
            'every_count' => 10,
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ]);

        $this->assertNull(app(ReviewBaxxService::class)->getProminentSpecialOffer());

        ReviewBaxxSpecialOffer::query()->delete();

        ReviewBaxxSpecialOffer::create([
            'points' => 5,
            'every_count' => 2,
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ]);

        $banner = app(ReviewBaxxService::class)->getProminentSpecialOffer();

        $this->assertNotNull($banner);
        $this->assertSame('2,5', $banner['points_per_review_label']);
        $this->assertStringContainsString('Special Offer', $banner['banner_text']);
    }

    public function test_award_points_for_review_uses_effective_rule_interval(): void
    {
        $user = $this->createUserWithRole('Mitglied');

        ReviewBaxxSpecialOffer::create([
            'points' => 2,
            'every_count' => 1,
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ]);

        $awardedPoints = app(ReviewBaxxService::class)->awardPointsForReview($user, 1);

        $this->assertSame(2, $awardedPoints);
        $this->assertDatabaseHas('user_points', [
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'points' => 2,
        ]);
        $this->assertSame(1, UserPoint::count());
    }
}