<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Enums\Role;
use App\Models\Activity;
use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Models\Team;
use App\Models\UserPoint;
use App\Services\RewardService;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RewardServiceTestFixtures::class);

beforeEach(function (): void {
    $this->service = app(RewardService::class);
});

test('purchase reward succeeds with enough baxx', function (): void {
    $user = $this->actingMemberWithPoints(10);
    $reward = Reward::factory()->create(['cost_baxx' => 5]);
    $membersTeam = Team::membersTeam();
    expect($membersTeam)->not->toBeNull();
    $purchase = $this->service->purchaseReward($user, $reward);
    expect($purchase)->not->toBeNull()
        ->and($purchase->user_id)->toEqual($user->id)
        ->and($purchase->reward_id)->toEqual($reward->id)
        ->and($purchase->wallet_team_id)->toEqual($membersTeam->id)
        ->and($purchase->cost_baxx)->toEqual(5)
        ->and($purchase->purchased_at)->not->toBeNull()
        ->and($purchase->refunded_at)->toBeNull();
});

test('purchase reward creates activity for unlocked reward', function (): void {
    $user = $this->actingMemberWithPoints(10);
    $reward = Reward::factory()->create(['cost_baxx' => 5]);
    $purchase = $this->service->purchaseReward($user, $reward);
    $this->assertDatabaseHas('activities', ['user_id' => $user->id, 'subject_type' => RewardPurchase::class, 'subject_id' => $purchase->id, 'action' => 'reward_unlocked']);
    expect(Activity::query()->where('subject_type', RewardPurchase::class)->where('subject_id', $purchase->id)->where('action', 'reward_unlocked')->count())->toBe(1);
});

test('purchase reward fails with insufficient baxx', function (): void {
    $user = $this->actingMemberWithPoints(3);
    $reward = Reward::factory()->create(['cost_baxx' => 10]);
    expect(fn () => $this->service->purchaseReward($user, $reward))->toThrow(ValidationException::class);
});

test('purchase reward fails when already purchased', function (): void {
    $user = $this->actingMemberWithPoints(20);
    $reward = Reward::factory()->create(['cost_baxx' => 5]);
    $this->service->purchaseReward($user, $reward);
    expect(fn () => $this->service->purchaseReward($user, $reward))->toThrow(ValidationException::class);
});

test('purchase reward fails when reward inactive', function (): void {
    $user = $this->actingMemberWithPoints(20);
    $reward = Reward::factory()->inactive()->create(['cost_baxx' => 5]);
    expect(fn () => $this->service->purchaseReward($user, $reward))->toThrow(ValidationException::class);
});

test('purchase after refund allows repurchase', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $user = $this->actingMemberWithPoints(20);
    $reward = Reward::factory()->create(['cost_baxx' => 5]);
    $purchase = $this->service->purchaseReward($user, $reward);
    $this->service->refundPurchase($purchase, $admin);
    // Should be able to repurchase after refund
    $newPurchase = $this->service->purchaseReward($user, $reward);
    expect($newPurchase)->not->toBeNull();
});

test('refund purchase sets refunded fields', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $user = $this->actingMemberWithPoints(10);
    $reward = Reward::factory()->create(['cost_baxx' => 5]);
    $purchase = $this->service->purchaseReward($user, $reward);
    $this->service->refundPurchase($purchase, $admin);
    $purchase->refresh();
    expect($purchase->refunded_at)->not->toBeNull()
        ->and($purchase->refunded_by)->toEqual($admin->id);
});

test('refund already refunded throws exception', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $user = $this->actingMemberWithPoints(10);
    $reward = Reward::factory()->create(['cost_baxx' => 5]);
    $purchase = $this->service->purchaseReward($user, $reward);
    $this->service->refundPurchase($purchase, $admin);
    expect(fn () => $this->service->refundPurchase($purchase, $admin))->toThrow(ValidationException::class);
});

test('stale purchase cannot overwrite an existing refund', function (): void {
    $firstAdmin = $this->createUserWithRole(Role::Admin);
    $secondAdmin = $this->createUserWithRole(Role::Admin);
    $user = $this->actingMemberWithPoints(10);
    $purchase = $this->service->purchaseReward($user, Reward::factory()->create(['cost_baxx' => 5]));
    $stale = $purchase->fresh();
    $this->service->refundPurchase($purchase, $firstAdmin);
    try {
        $this->service->refundPurchase($stale, $secondAdmin);
        $this->fail('Repeated refund must be rejected.');
    } catch (ValidationException) {
        expect($purchase->fresh()->refunded_by)->toBe($firstAdmin->id)
            ->and($this->service->getAvailableBaxx($user))->toBe(10);
    }
});

test('get available baxx subtracts spent', function (): void {
    $user = $this->actingMemberWithPoints(20);
    $reward = Reward::factory()->create(['cost_baxx' => 7]);
    $this->service->purchaseReward($user, $reward);
    $available = $this->service->getAvailableBaxx($user);
    expect($available)->toEqual(13);
});

test('get available baxx refund restores balance', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $user = $this->actingMemberWithPoints(20);
    $reward = Reward::factory()->create(['cost_baxx' => 7]);
    $purchase = $this->service->purchaseReward($user, $reward);
    expect($this->service->getAvailableBaxx($user))->toEqual(13);
    $this->service->refundPurchase($purchase, $admin);
    expect($this->service->getAvailableBaxx($user))->toEqual(20);
});

test('get available baxx uses members team when current team differs', function (): void {
    expect(Team::membersTeam())->toBeInstanceOf(Team::class);
    $user = $this->actingMemberWithPoints(20);
    $otherTeam = Team::factory()->create(['personal_team' => false]);
    $otherTeam->users()->attach($user, ['role' => Role::Mitglied->value]);
    UserPoint::create(['user_id' => $user->id, 'team_id' => $otherTeam->id, 'todo_id' => null, 'points' => 50]);
    $userWithOtherCurrentTeam = $user->fresh();
    $userWithOtherCurrentTeam->current_team_id = $otherTeam->id;
    $userWithOtherCurrentTeam->unsetRelation('currentTeam');
    expect($this->service->getAvailableBaxx($userWithOtherCurrentTeam))->toEqual(20);
});

test('get wallet state marks legacy purchase as ambiguous', function (): void {
    $user = $this->actingMemberWithPoints(20);
    $reward = Reward::factory()->create(['cost_baxx' => 7]);
    RewardPurchase::factory()->create(['user_id' => $user->id, 'reward_id' => $reward->id, 'wallet_team_id' => null, 'cost_baxx' => 7]);
    $walletState = $this->service->getWalletState($user);
    expect($walletState['status'])->toBe('ambiguous-legacy')
        ->and($walletState['earnedBaxx'])->toBe(20)
        ->and($walletState['spentBaxx'])->toBeNull()
        ->and($walletState['availableBaxx'])->toBeNull()
        ->and($walletState['warning'])->not->toBeNull();
});

test('purchase reward uses members team balance even with multi team points', function (): void {
    $user = $this->actingMemberWithPoints(20);
    $otherTeam = Team::factory()->create(['personal_team' => false]);
    $otherTeam->users()->attach($user, ['role' => Role::Mitglied->value]);
    UserPoint::create(['user_id' => $user->id, 'team_id' => $otherTeam->id, 'todo_id' => null, 'points' => 50]);
    $userWithOtherCurrentTeam = $user->fresh();
    $userWithOtherCurrentTeam->current_team_id = $otherTeam->id;
    $userWithOtherCurrentTeam->unsetRelation('currentTeam');
    $reward = Reward::factory()->create(['cost_baxx' => 7]);
    $this->service->purchaseReward($userWithOtherCurrentTeam, $reward);
    expect($this->service->getAvailableBaxx($userWithOtherCurrentTeam->fresh()))->toEqual(13);
});

test('purchase reward fails when only other team has points', function (): void {
    $user = $this->actingMember();
    $otherTeam = Team::factory()->create(['personal_team' => false]);
    $otherTeam->users()->attach($user, ['role' => Role::Mitglied->value]);
    UserPoint::create(['user_id' => $user->id, 'team_id' => $otherTeam->id, 'todo_id' => null, 'points' => 20]);
    $userWithOtherCurrentTeam = $user->fresh();
    $userWithOtherCurrentTeam->current_team_id = $otherTeam->id;
    $userWithOtherCurrentTeam->unsetRelation('currentTeam');
    $reward = Reward::factory()->create(['cost_baxx' => 7]);
    expect(fn () => $this->service->purchaseReward($userWithOtherCurrentTeam, $reward))->toThrow(ValidationException::class);
});

test('purchase reward fails when legacy purchase is ambiguous', function (): void {
    $user = $this->actingMemberWithPoints(20);
    $reward = Reward::factory()->create(['cost_baxx' => 7]);
    RewardPurchase::factory()->create(['user_id' => $user->id, 'reward_id' => $reward->id, 'wallet_team_id' => null, 'cost_baxx' => 7]);
    expect(fn () => $this->service->purchaseReward($user, $reward))->toThrow(ValidationException::class);
});

test('get wallet state reports missing members team without throwing', function (): void {
    $user = $this->actingMemberWithPoints(20);
    Team::membersTeam()?->delete();
    $walletState = $this->service->getWalletState($user->fresh());
    expect($walletState['status'])->toBe('missing-members-team')
        ->and($walletState['availableBaxx'])->toBeNull()
        ->and($walletState['spentBaxx'])->toBeNull()
        ->and($walletState['warning'])->not->toBeNull();
});

test('get available baxx throws when members team is missing', function (): void {
    $user = $this->actingMemberWithPoints(20);
    Team::membersTeam()?->delete();
    expect(fn () => $this->service->getAvailableBaxx($user->fresh()))->toThrow(LogicException::class);
});

test('get spent baxx excludes refunded', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $user = $this->actingMemberWithPoints(30);
    $reward1 = Reward::factory()->create(['cost_baxx' => 5]);
    $reward2 = Reward::factory()->create(['cost_baxx' => 10]);
    $purchase1 = $this->service->purchaseReward($user, $reward1);
    $this->service->purchaseReward($user, $reward2);
    expect($this->service->getSpentBaxx($user))->toEqual(15);
    $this->service->refundPurchase($purchase1, $admin);
    expect($this->service->getSpentBaxx($user))->toEqual(10);
});

test('has unlocked reward returns true for purchased', function (): void {
    $user = $this->actingMemberWithPoints(10);
    $reward = Reward::factory()->create(['cost_baxx' => 5, 'slug' => 'test-feature']);
    $this->service->purchaseReward($user, $reward);
    expect($this->service->hasUnlockedReward($user, 'test-feature'))->toBeTrue();
});

test('has unlocked reward accepts legacy kompendium slug', function (): void {
    $user = $this->actingMemberWithPoints(120);
    $legacyReward = Reward::updateOrCreate(['slug' => 'kompendium-suche'], ['title' => 'Kompendium-Suche', 'description' => 'Legacy-Zugang zur Kompendium-Suche.', 'category' => 'Kompendium', 'cost_baxx' => 100, 'is_active' => true, 'sort_order' => 0]);
    $this->service->purchaseReward($user, $legacyReward);
    expect($this->service->hasUnlockedReward($user, 'kompendium'))->toBeTrue();
});

test('has unlocked reward returns false for not purchased', function (): void {
    $user = $this->actingMemberWithPoints(10);
    Reward::factory()->create(['cost_baxx' => 5, 'slug' => 'test-feature']);
    expect($this->service->hasUnlockedReward($user, 'test-feature'))->toBeFalse();
});

test('has unlocked reward returns false for refunded', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $user = $this->actingMemberWithPoints(10);
    $reward = Reward::factory()->create(['cost_baxx' => 5, 'slug' => 'test-feature']);
    $purchase = $this->service->purchaseReward($user, $reward);
    $this->service->refundPurchase($purchase, $admin);
    expect($this->service->hasUnlockedReward($user, 'test-feature'))->toBeFalse();
});

test('has unlocked reward returns false for unknown slug', function (): void {
    $user = $this->actingMemberWithPoints(10);
    expect($this->service->hasUnlockedReward($user, 'nonexistent-slug'))->toBeFalse();
});

test('get admin statistics returns correct totals', function (): void {
    $user1 = $this->actingMemberWithPoints(50);
    $user2 = $this->createUserWithRole(Role::Mitglied);
    $user2->incrementTeamPoints(30);
    $reward1 = Reward::factory()->create(['cost_baxx' => 5]);
    $reward2 = Reward::factory()->create(['cost_baxx' => 10]);
    $reward3 = Reward::factory()->create(['cost_baxx' => 3]);
    $this->service->purchaseReward($user1, $reward1);
    $this->service->purchaseReward($user1, $reward2);
    $this->service->purchaseReward($user2, $reward1);
    $stats = $this->service->getAdminStatistics();
    expect($stats['total_spent_baxx'])->toEqual(20);
    // 5 + 10 + 5
    expect($stats['never_purchased_rewards']->contains('id', $reward3->id))->toBeTrue();
});
