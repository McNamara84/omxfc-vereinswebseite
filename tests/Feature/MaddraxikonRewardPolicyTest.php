<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MaddraxikonContribution;
use App\Models\MaddraxikonRewardPolicy;
use App\Models\MaddraxikonRewardPolicyTier;
use App\Models\User;
use App\Services\Maddraxikon\MaddraxikonEditSessionRewardCalculator;
use App\Services\Maddraxikon\MaddraxikonRewardPolicyPublisher;
use App\Services\Maddraxikon\MaddraxikonRewardPolicyResolver;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(MaddraxikonRewardPolicyTestFixtures::class);

test('calculator orders equal instants by revision id before selecting boundary sizes', function (): void {
    $contributions = collect([new MaddraxikonContribution(['occurred_at_epoch' => 101, 'revision_id' => 9, 'old_size' => 70, 'new_size' => 100]), new MaddraxikonContribution(['occurred_at_epoch' => 100, 'revision_id' => 2, 'old_size' => 20, 'new_size' => 70]), new MaddraxikonContribution(['occurred_at_epoch' => 100, 'revision_id' => 1, 'old_size' => 10, 'new_size' => 20])]);
    $policy = new MaddraxikonRewardPolicy(['edit_sessions_enabled' => false]);
    $result = app(MaddraxikonEditSessionRewardCalculator::class)->calculate($contributions, $policy);
    expect($result->startSize)->toBe(10)
        ->and($result->endSize)->toBe(100)
        ->and($result->addedBytes)->toBe(90);
});

test('calculator sums supplied revision deltas and uses highest matching tier', function (): void {
    $policy = MaddraxikonRewardPolicy::factory()->create();
    MaddraxikonRewardPolicyTier::factory()->create(['maddraxikon_reward_policy_id' => $policy->id, 'minimum_added_bytes' => 100, 'points' => 1]);
    $expectedTier = MaddraxikonRewardPolicyTier::factory()->create(['maddraxikon_reward_policy_id' => $policy->id, 'minimum_added_bytes' => 500, 'points' => 3]);
    MaddraxikonRewardPolicyTier::factory()->create(['maddraxikon_reward_policy_id' => $policy->id, 'minimum_added_bytes' => 1000, 'points' => 5]);
    $first = MaddraxikonContribution::factory()->make(['old_size' => 600, 'new_size' => 1200, 'occurred_at' => now()->subHours(2), 'revision_id' => 10]);
    $last = MaddraxikonContribution::factory()->make(['old_size' => 1500, 'new_size' => 1650, 'occurred_at' => now()->subHour(), 'revision_id' => 11]);
    $result = app(MaddraxikonEditSessionRewardCalculator::class)->calculate(collect([$last, $first]), $policy);
    expect($result->startSize)->toBe(600)
        ->and($result->endSize)->toBe(1650)
        ->and($result->addedBytes)->toBe(750)
        ->and($result->tier?->id)->toBe($expectedTier->id)
        ->and($result->candidatePoints)->toBe(3)
        ->and($result->statusReason)->toBeNull();
});

test('calculator never rewards negative growth or a value below first tier', function (): void {
    $policy = MaddraxikonRewardPolicy::factory()->create();
    MaddraxikonRewardPolicyTier::factory()->create(['maddraxikon_reward_policy_id' => $policy->id, 'minimum_added_bytes' => 100, 'points' => 2]);
    $contribution = MaddraxikonContribution::factory()->make(['old_size' => 800, 'new_size' => 700]);
    $result = app(MaddraxikonEditSessionRewardCalculator::class)->calculate(collect([$contribution]), $policy);
    expect($result->addedBytes)->toBe(0)
        ->and($result->candidatePoints)->toBe(0)
        ->and($result->tier)->toBeNull()
        ->and($result->statusReason)->toBe('below_minimum_edit_size');
});

test('calculator fails closed when any contribution size is missing', function (): void {
    $policy = MaddraxikonRewardPolicy::factory()->create();
    $first = MaddraxikonContribution::factory()->make(['old_size' => 500, 'new_size' => 600, 'occurred_at' => now()->subHours(2), 'revision_id' => 10]);
    $middle = MaddraxikonContribution::factory()->make(['old_size' => 600, 'new_size' => null, 'occurred_at' => now()->subMinutes(90), 'revision_id' => 11]);
    $last = MaddraxikonContribution::factory()->make(['old_size' => 700, 'new_size' => 800, 'occurred_at' => now()->subHour(), 'revision_id' => 12]);
    expect(fn () => app(MaddraxikonEditSessionRewardCalculator::class)->calculate(collect([$last, $middle, $first]), $policy))->toThrow(LogicException::class, 'revision_size_unavailable');
});

test('resolver uses the activity instant including exact boundary', function (): void {
    $boundary = CarbonImmutable::parse('2026-08-10T12:00:00Z');
    $old = $this->publishedPolicy('Alt', $boundary->subDay());
    $new = $this->publishedPolicy('Neu', $boundary);
    $resolver = app(MaddraxikonRewardPolicyResolver::class);
    expect($resolver->resolve($boundary->subSecond())?->id)->toBe($old->id)
        ->and($resolver->resolve($boundary)?->id)->toBe($new->id)
        ->and($resolver->resolve($boundary->addDay())?->id)->toBe($new->id);
});

test('resolver ignores drafts and future policies', function (): void {
    $now = CarbonImmutable::parse('2026-08-10T12:00:00Z');
    MaddraxikonRewardPolicy::factory()->create(['status' => MaddraxikonRewardPolicy::STATUS_DRAFT, 'effective_from' => $now->subDay()]);
    $this->publishedPolicy('Zukunft', $now->addDay());
    expect(app(MaddraxikonRewardPolicyResolver::class)->resolve($now))->toBeNull();
});

test('publisher records actor and makes policy and tiers immutable', function (): void {
    $now = CarbonImmutable::parse('2026-08-10T12:00:00Z');
    $admin = User::factory()->create();
    $policy = MaddraxikonRewardPolicy::factory()->create(['effective_from' => $now->addHour()]);
    $tier = MaddraxikonRewardPolicyTier::factory()->create(['maddraxikon_reward_policy_id' => $policy->id, 'minimum_added_bytes' => 100, 'points' => 2]);
    $published = app(MaddraxikonRewardPolicyPublisher::class)->publish($policy, $admin, $now);
    expect($published->isPublished())->toBeTrue()
        ->and($published->published_by)->toBe($admin->id)
        ->and($published->published_at?->equalTo($now))->toBeTrue();
    try {
        $published->update(['name' => 'Manipuliert']);
        $this->fail('Veröffentlichte Policies müssen unveränderlich sein.');
    } catch (LogicException $exception) {
        expect($exception->getMessage())->toContain('unveränderlich');
    }
    expect(fn () => $tier->update(['points' => 9]))->toThrow(LogicException::class);
});

test('publisher rejects past policy and missing tiers', function (): void {
    $now = CarbonImmutable::parse('2026-08-10T12:00:00Z');
    $admin = User::factory()->create();
    $policy = MaddraxikonRewardPolicy::factory()->create(['effective_from' => $now]);
    try {
        app(MaddraxikonRewardPolicyPublisher::class)->publish($policy, $admin, $now);
        $this->fail('Vergangene Policies dürfen nicht veröffentlicht werden.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('policyEffectiveFrom');
    }
    $policy->update(['effective_from' => $now->addHour()]);
    expect(fn () => app(MaddraxikonRewardPolicyPublisher::class)->publish($policy, $admin, $now))->toThrow(ValidationException::class);
});

test('atomic draft publication prevents a stale admin from replacing approved content', function (): void {
    $now = CarbonImmutable::parse('2026-08-10T12:00:00Z');
    $firstAdmin = User::factory()->create();
    $staleAdmin = User::factory()->create();
    $policy = MaddraxikonRewardPolicy::factory()->create(['name' => 'Ursprünglicher Entwurf', 'effective_from' => $now->addHours(2)]);
    MaddraxikonRewardPolicyTier::factory()->create(['maddraxikon_reward_policy_id' => $policy->id, 'minimum_added_bytes' => 50, 'points' => 1]);
    $publisher = app(MaddraxikonRewardPolicyPublisher::class);
    $published = $publisher->publishDraft($policy->id, ['name' => 'Von Admin A freigegeben', 'effective_from' => $now->addHours(3), 'edit_sessions_enabled' => true, 'new_articles_enabled' => true, 'new_article_minimum_bytes' => 800, 'new_article_points' => 6], [['minimum_added_bytes' => 100, 'points' => 2]], $firstAdmin, $now);
    try {
        $publisher->publishDraft($policy->id, ['name' => 'Veraltete Eingabe von Admin B', 'effective_from' => $now->addHours(4), 'edit_sessions_enabled' => true, 'new_articles_enabled' => true, 'new_article_minimum_bytes' => 100, 'new_article_points' => 99], [['minimum_added_bytes' => 10, 'points' => 99]], $staleAdmin, $now);
        $this->fail('Ein veralteter Request darf eine veröffentlichte Policy nicht ersetzen.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('policy');
    }
    $published->refresh();
    expect($published->name)->toBe('Von Admin A freigegeben')
        ->and($published->published_by)->toBe($firstAdmin->id)
        ->and($published->new_article_minimum_bytes)->toBe(800)
        ->and($published->new_article_points)->toBe(6)
        ->and($published->tiers()->get()->map(fn (MaddraxikonRewardPolicyTier $tier): array => [$tier->minimum_added_bytes, $tier->points])->all())->toBe([[100, 2]]);
});

test('atomic draft publication rolls back submitted content when publication fails', function (): void {
    $now = CarbonImmutable::parse('2026-08-10T12:00:00Z');
    $admin = User::factory()->create();
    $originalEffectiveFrom = $now->addHours(2);
    $policy = MaddraxikonRewardPolicy::factory()->create(['name' => 'Unveränderter Entwurf', 'effective_from' => $originalEffectiveFrom, 'new_article_minimum_bytes' => 500, 'new_article_points' => 5]);
    MaddraxikonRewardPolicyTier::factory()->create(['maddraxikon_reward_policy_id' => $policy->id, 'minimum_added_bytes' => 100, 'points' => 1]);
    try {
        app(MaddraxikonRewardPolicyPublisher::class)->publishDraft($policy->id, ['name' => 'Darf nicht gespeichert werden', 'effective_from' => $now, 'edit_sessions_enabled' => true, 'new_articles_enabled' => true, 'new_article_minimum_bytes' => 900, 'new_article_points' => 9], [['minimum_added_bytes' => 900, 'points' => 9]], $admin, $now);
        $this->fail('Eine ungültige Veröffentlichung muss vollständig zurückgerollt werden.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('policyEffectiveFrom');
    }
    $policy->refresh();
    expect($policy->status)->toBe(MaddraxikonRewardPolicy::STATUS_DRAFT)
        ->and($policy->name)->toBe('Unveränderter Entwurf')
        ->and($policy->effective_from_epoch)->toBe($originalEffectiveFrom->getTimestamp())
        ->and($policy->new_article_minimum_bytes)->toBe(500)
        ->and($policy->new_article_points)->toBe(5)
        ->and($policy->tiers()->get()->map(fn (MaddraxikonRewardPolicyTier $tier): array => [$tier->minimum_added_bytes, $tier->points])->all())->toBe([[100, 1]]);
});

test('tier cannot be moved from a published policy to a draft', function (): void {
    $now = CarbonImmutable::parse('2026-08-10T12:00:00Z');
    $admin = User::factory()->create();
    $publishedPolicy = MaddraxikonRewardPolicy::factory()->create(['effective_from' => $now->addHour()]);
    $tier = MaddraxikonRewardPolicyTier::factory()->create(['maddraxikon_reward_policy_id' => $publishedPolicy->id, 'minimum_added_bytes' => 100, 'points' => 2]);
    app(MaddraxikonRewardPolicyPublisher::class)->publish($publishedPolicy, $admin, $now);
    $draftPolicy = MaddraxikonRewardPolicy::factory()->create(['effective_from' => $now->addHours(2)]);
    try {
        $tier->update(['maddraxikon_reward_policy_id' => $draftPolicy->id]);
        $this->fail('Stufen dürfen eine veröffentlichte Policy nicht verlassen.');
    } catch (LogicException $exception) {
        expect($exception->getMessage())->toContain('unveränderlich');
    }
    $this->assertDatabaseHas('maddraxikon_reward_policy_tiers', ['id' => $tier->id, 'maddraxikon_reward_policy_id' => $publishedPolicy->id]);
});

test('legacy rules are present without running a manual seeder', function (): void {
    $this->assertDatabaseHas('baxx_earning_rules', ['action_key' => 'maddraxikon_edit_session', 'points' => 1, 'every_count' => 5]);
    $this->assertDatabaseHas('baxx_earning_rules', ['action_key' => 'maddraxikon_new_article', 'points' => 5, 'every_count' => 1]);
});
