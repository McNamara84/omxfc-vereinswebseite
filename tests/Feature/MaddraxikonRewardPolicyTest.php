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

    public function test_calculator_sums_supplied_revision_deltas_and_uses_highest_matching_tier(): void
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
            'old_size' => 1500,
            'new_size' => 1650,
            'occurred_at' => now()->subHour(),
            'revision_id' => 11,
        ]);

        $result = app(MaddraxikonEditSessionRewardCalculator::class)
            ->calculate(collect([$last, $first]), $policy);

        $this->assertSame(600, $result->startSize);
        $this->assertSame(1650, $result->endSize);
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

    public function test_calculator_fails_closed_when_any_contribution_size_is_missing(): void
    {
        $policy = MaddraxikonRewardPolicy::factory()->create();
        $first = MaddraxikonContribution::factory()->make([
            'old_size' => 500,
            'new_size' => 600,
            'occurred_at' => now()->subHours(2),
            'revision_id' => 10,
        ]);
        $middle = MaddraxikonContribution::factory()->make([
            'old_size' => 600,
            'new_size' => null,
            'occurred_at' => now()->subMinutes(90),
            'revision_id' => 11,
        ]);
        $last = MaddraxikonContribution::factory()->make([
            'old_size' => 700,
            'new_size' => 800,
            'occurred_at' => now()->subHour(),
            'revision_id' => 12,
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('revision_size_unavailable');

        app(MaddraxikonEditSessionRewardCalculator::class)
            ->calculate(collect([$last, $middle, $first]), $policy);
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

    public function test_atomic_draft_publication_prevents_a_stale_admin_from_replacing_approved_content(): void
    {
        $now = CarbonImmutable::parse('2026-08-10T12:00:00Z');
        $firstAdmin = User::factory()->create();
        $staleAdmin = User::factory()->create();
        $policy = MaddraxikonRewardPolicy::factory()->create([
            'name' => 'Ursprünglicher Entwurf',
            'effective_from' => $now->addHours(2),
        ]);
        MaddraxikonRewardPolicyTier::factory()->create([
            'maddraxikon_reward_policy_id' => $policy->id,
            'minimum_added_bytes' => 50,
            'points' => 1,
        ]);
        $publisher = app(MaddraxikonRewardPolicyPublisher::class);

        $published = $publisher->publishDraft(
            $policy->id,
            [
                'name' => 'Von Admin A freigegeben',
                'effective_from' => $now->addHours(3),
                'edit_sessions_enabled' => true,
                'new_articles_enabled' => true,
                'new_article_minimum_bytes' => 800,
                'new_article_points' => 6,
            ],
            [
                ['minimum_added_bytes' => 100, 'points' => 2],
            ],
            $firstAdmin,
            $now,
        );

        try {
            $publisher->publishDraft(
                $policy->id,
                [
                    'name' => 'Veraltete Eingabe von Admin B',
                    'effective_from' => $now->addHours(4),
                    'edit_sessions_enabled' => true,
                    'new_articles_enabled' => true,
                    'new_article_minimum_bytes' => 100,
                    'new_article_points' => 99,
                ],
                [
                    ['minimum_added_bytes' => 10, 'points' => 99],
                ],
                $staleAdmin,
                $now,
            );
            $this->fail('Ein veralteter Request darf eine veröffentlichte Policy nicht ersetzen.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('policy', $exception->errors());
        }

        $published->refresh();
        $this->assertSame('Von Admin A freigegeben', $published->name);
        $this->assertSame($firstAdmin->id, $published->published_by);
        $this->assertSame(800, $published->new_article_minimum_bytes);
        $this->assertSame(6, $published->new_article_points);
        $this->assertSame(
            [[100, 2]],
            $published->tiers()
                ->get()
                ->map(fn (MaddraxikonRewardPolicyTier $tier): array => [
                    $tier->minimum_added_bytes,
                    $tier->points,
                ])
                ->all()
        );
    }

    public function test_atomic_draft_publication_rolls_back_submitted_content_when_publication_fails(): void
    {
        $now = CarbonImmutable::parse('2026-08-10T12:00:00Z');
        $admin = User::factory()->create();
        $originalEffectiveFrom = $now->addHours(2);
        $policy = MaddraxikonRewardPolicy::factory()->create([
            'name' => 'Unveränderter Entwurf',
            'effective_from' => $originalEffectiveFrom,
            'new_article_minimum_bytes' => 500,
            'new_article_points' => 5,
        ]);
        MaddraxikonRewardPolicyTier::factory()->create([
            'maddraxikon_reward_policy_id' => $policy->id,
            'minimum_added_bytes' => 100,
            'points' => 1,
        ]);

        try {
            app(MaddraxikonRewardPolicyPublisher::class)->publishDraft(
                $policy->id,
                [
                    'name' => 'Darf nicht gespeichert werden',
                    'effective_from' => $now,
                    'edit_sessions_enabled' => true,
                    'new_articles_enabled' => true,
                    'new_article_minimum_bytes' => 900,
                    'new_article_points' => 9,
                ],
                [
                    ['minimum_added_bytes' => 900, 'points' => 9],
                ],
                $admin,
                $now,
            );
            $this->fail('Eine ungültige Veröffentlichung muss vollständig zurückgerollt werden.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('policyEffectiveFrom', $exception->errors());
        }

        $policy->refresh();
        $this->assertSame(MaddraxikonRewardPolicy::STATUS_DRAFT, $policy->status);
        $this->assertSame('Unveränderter Entwurf', $policy->name);
        $this->assertSame(
            $originalEffectiveFrom->getTimestamp(),
            $policy->effective_from_epoch
        );
        $this->assertSame(500, $policy->new_article_minimum_bytes);
        $this->assertSame(5, $policy->new_article_points);
        $this->assertSame(
            [[100, 1]],
            $policy->tiers()
                ->get()
                ->map(fn (MaddraxikonRewardPolicyTier $tier): array => [
                    $tier->minimum_added_bytes,
                    $tier->points,
                ])
                ->all()
        );
    }

    public function test_tier_cannot_be_moved_from_a_published_policy_to_a_draft(): void
    {
        $now = CarbonImmutable::parse('2026-08-10T12:00:00Z');
        $admin = User::factory()->create();
        $publishedPolicy = MaddraxikonRewardPolicy::factory()->create([
            'effective_from' => $now->addHour(),
        ]);
        $tier = MaddraxikonRewardPolicyTier::factory()->create([
            'maddraxikon_reward_policy_id' => $publishedPolicy->id,
            'minimum_added_bytes' => 100,
            'points' => 2,
        ]);
        app(MaddraxikonRewardPolicyPublisher::class)
            ->publish($publishedPolicy, $admin, $now);
        $draftPolicy = MaddraxikonRewardPolicy::factory()->create([
            'effective_from' => $now->addHours(2),
        ]);

        try {
            $tier->update([
                'maddraxikon_reward_policy_id' => $draftPolicy->id,
            ]);
            $this->fail('Stufen dürfen eine veröffentlichte Policy nicht verlassen.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('unveränderlich', $exception->getMessage());
        }

        $this->assertDatabaseHas('maddraxikon_reward_policy_tiers', [
            'id' => $tier->id,
            'maddraxikon_reward_policy_id' => $publishedPolicy->id,
        ]);
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
