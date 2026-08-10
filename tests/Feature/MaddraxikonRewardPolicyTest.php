<?php

namespace Tests\Feature;

use App\Models\MaddraxikonContribution;
use App\Models\MaddraxikonRewardPolicy;
use App\Models\MaddraxikonRewardPolicyTier;
use App\Models\User;
use App\Services\Maddraxikon\MaddraxikonEditSessionRewardCalculator;
use App\Services\Maddraxikon\MaddraxikonRewardPolicyPublisher;
use App\Services\Maddraxikon\MaddraxikonRewardPolicyResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class MaddraxikonRewardPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculator_uses_session_net_growth_and_highest_matching_tier(): void
    {
        $policy = MaddraxikonRewardPolicy::factory()->create();
        MaddraxikonRewardPolicyTier::factory()->create([
            'maddraxikon_reward_policy_id' => $policy->id,
            'minimum_added_bytes' => 100,
            'points' => 1,
        ]);
        $expectedTier = MaddraxikonRewardPolicyTier::factory()->create([
            'maddraxikon_reward_policy_id' => $policy->id,
            'minimum_added_bytes' => 500,
            'points' => 3,
        ]);
        MaddraxikonRewardPolicyTier::factory()->create([
            'maddraxikon_reward_policy_id' => $policy->id,
            'minimum_added_bytes' => 1000,
            'points' => 5,
        ]);
        $first = MaddraxikonContribution::factory()->make([
            'old_size' => 600,
            'new_size' => 1200,
            'occurred_at' => now()->subHours(2),
            'revision_id' => 10,
        ]);
        $last = MaddraxikonContribution::factory()->make([
            'old_size' => 1200,
            'new_size' => 1350,
            'occurred_at' => now()->subHour(),
            'revision_id' => 11,
        ]);

        $result = app(MaddraxikonEditSessionRewardCalculator::class)
            ->calculate(collect([$last, $first]), $policy);

        $this->assertSame(600, $result->startSize);
        $this->assertSame(1350, $result->endSize);
        $this->assertSame(750, $result->addedBytes);
        $this->assertSame($expectedTier->id, $result->tier?->id);
        $this->assertSame(3, $result->candidatePoints);
        $this->assertNull($result->statusReason);
    }

    public function test_calculator_never_rewards_negative_growth_or_a_value_below_first_tier(): void
    {
        $policy = MaddraxikonRewardPolicy::factory()->create();
        MaddraxikonRewardPolicyTier::factory()->create([
            'maddraxikon_reward_policy_id' => $policy->id,
            'minimum_added_bytes' => 100,
            'points' => 2,
        ]);
        $contribution = MaddraxikonContribution::factory()->make([
            'old_size' => 800,
            'new_size' => 700,
        ]);

        $result = app(MaddraxikonEditSessionRewardCalculator::class)
            ->calculate(collect([$contribution]), $policy);

        $this->assertSame(0, $result->addedBytes);
        $this->assertSame(0, $result->candidatePoints);
        $this->assertNull($result->tier);
        $this->assertSame('below_minimum_edit_size', $result->statusReason);
    }

    public function test_calculator_fails_closed_when_a_boundary_size_is_missing(): void
    {
        $policy = MaddraxikonRewardPolicy::factory()->create();
        $contribution = MaddraxikonContribution::factory()->make([
            'old_size' => null,
            'new_size' => 700,
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('revision_size_unavailable');

        app(MaddraxikonEditSessionRewardCalculator::class)
            ->calculate(collect([$contribution]), $policy);
    }

    public function test_resolver_uses_the_activity_instant_including_exact_boundary(): void
    {
        $boundary = CarbonImmutable::parse('2026-08-10T12:00:00Z');
        $old = $this->publishedPolicy('Alt', $boundary->subDay());
        $new = $this->publishedPolicy('Neu', $boundary);
        $resolver = app(MaddraxikonRewardPolicyResolver::class);

        $this->assertSame(
            $old->id,
            $resolver->resolve($boundary->subSecond())?->id
        );
        $this->assertSame($new->id, $resolver->resolve($boundary)?->id);
        $this->assertSame($new->id, $resolver->resolve($boundary->addDay())?->id);
    }

    public function test_resolver_ignores_drafts_and_future_policies(): void
    {
        $now = CarbonImmutable::parse('2026-08-10T12:00:00Z');
        MaddraxikonRewardPolicy::factory()->create([
            'status' => MaddraxikonRewardPolicy::STATUS_DRAFT,
            'effective_from' => $now->subDay(),
        ]);
        $this->publishedPolicy('Zukunft', $now->addDay());

        $this->assertNull(
            app(MaddraxikonRewardPolicyResolver::class)->resolve($now)
        );
    }

    public function test_publisher_records_actor_and_makes_policy_and_tiers_immutable(): void
    {
        $now = CarbonImmutable::parse('2026-08-10T12:00:00Z');
        $admin = User::factory()->create();
        $policy = MaddraxikonRewardPolicy::factory()->create([
            'effective_from' => $now->addHour(),
        ]);
        $tier = MaddraxikonRewardPolicyTier::factory()->create([
            'maddraxikon_reward_policy_id' => $policy->id,
            'minimum_added_bytes' => 100,
            'points' => 2,
        ]);

        $published = app(MaddraxikonRewardPolicyPublisher::class)
            ->publish($policy, $admin, $now);

        $this->assertTrue($published->isPublished());
        $this->assertSame($admin->id, $published->published_by);
        $this->assertTrue($published->published_at?->equalTo($now));

        try {
            $published->update(['name' => 'Manipuliert']);
            $this->fail('Veröffentlichte Policies müssen unveränderlich sein.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('unveränderlich', $exception->getMessage());
        }

        $this->expectException(LogicException::class);
        $tier->update(['points' => 9]);
    }

    public function test_publisher_rejects_past_policy_and_missing_tiers(): void
    {
        $now = CarbonImmutable::parse('2026-08-10T12:00:00Z');
        $admin = User::factory()->create();
        $policy = MaddraxikonRewardPolicy::factory()->create([
            'effective_from' => $now,
        ]);

        try {
            app(MaddraxikonRewardPolicyPublisher::class)
                ->publish($policy, $admin, $now);
            $this->fail('Vergangene Policies dürfen nicht veröffentlicht werden.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'policyEffectiveFrom',
                $exception->errors()
            );
        }

        $policy->update(['effective_from' => $now->addHour()]);

        $this->expectException(ValidationException::class);
        app(MaddraxikonRewardPolicyPublisher::class)
            ->publish($policy, $admin, $now);
    }

    public function test_legacy_rules_are_present_without_running_a_manual_seeder(): void
    {
        $this->assertDatabaseHas('baxx_earning_rules', [
            'action_key' => 'maddraxikon_edit_session',
            'points' => 1,
            'every_count' => 5,
        ]);
        $this->assertDatabaseHas('baxx_earning_rules', [
            'action_key' => 'maddraxikon_new_article',
            'points' => 5,
            'every_count' => 1,
        ]);
    }

    private function publishedPolicy(
        string $name,
        CarbonImmutable $effectiveFrom,
    ): MaddraxikonRewardPolicy {
        $policy = MaddraxikonRewardPolicy::factory()->create([
            'name' => $name,
            'effective_from' => $effectiveFrom,
        ]);
        $policy->update([
            'status' => MaddraxikonRewardPolicy::STATUS_PUBLISHED,
            'published_at' => $effectiveFrom->subMinute(),
        ]);

        return $policy->fresh();
    }
}
