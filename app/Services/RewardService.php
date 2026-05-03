<?php

namespace App\Services;

use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class RewardService
{
    public function __construct(
        private readonly TeamPointService $teamPointService
    ) {}

    /**
     * @return array{
     *     status: 'ok'|'ambiguous-legacy'|'missing-members-team',
     *     earnedBaxx: int,
     *     spentBaxx: ?int,
     *     availableBaxx: ?int,
     *     warning: ?string
     * }
     */
    public function getWalletState(User $user): array
    {
        $walletTeam = Team::membersTeam();

        if (! $walletTeam) {
            return [
                'status' => 'missing-members-team',
                'earnedBaxx' => 0,
                'spentBaxx' => null,
                'availableBaxx' => null,
                'warning' => $this->missingMembersTeamWarning(),
            ];
        }

        $earnedBaxx = $this->teamPointService->getUserPointsForTeam($user, $walletTeam);

        if ($this->hasAmbiguousLegacyPurchases($user)) {
            return [
                'status' => 'ambiguous-legacy',
                'earnedBaxx' => $earnedBaxx,
                'spentBaxx' => null,
                'availableBaxx' => null,
                'warning' => $this->ambiguousLegacyWarning(),
            ];
        }

        $spentBaxx = $this->getAssignedSpentBaxx($user, $walletTeam);

        return [
            'status' => 'ok',
            'earnedBaxx' => $earnedBaxx,
            'spentBaxx' => $spentBaxx,
            'availableBaxx' => max(0, $earnedBaxx - $spentBaxx),
            'warning' => null,
        ];
    }

    /**
     * Purchase a reward for a user.
     *
     * @throws ValidationException
     */
    public function purchaseReward(User $user, Reward $reward): RewardPurchase
    {
        if (! $reward->is_active) {
            throw ValidationException::withMessages([
                'reward' => 'Diese Belohnung ist derzeit nicht verfügbar.',
            ]);
        }

        return DB::transaction(function () use ($user, $reward) {
            RewardPurchase::where('user_id', $user->id)
                ->lockForUpdate()
                ->count();

            $existingPurchase = RewardPurchase::where('user_id', $user->id)
                ->where('reward_id', $reward->id)
                ->active()
                ->first();

            if ($existingPurchase) {
                throw ValidationException::withMessages([
                    'reward' => 'Du hast diese Belohnung bereits freigeschaltet.',
                ]);
            }

            $this->assertSpendableWallet($user);

            $walletTeam = $this->resolveRewardWalletTeam();
            $availableBaxx = $this->getAvailableBaxx($user);

            if ($availableBaxx < $reward->cost_baxx) {
                throw ValidationException::withMessages([
                    'reward' => "Du benötigst {$reward->cost_baxx} Baxx, hast aber nur {$availableBaxx} verfügbar.",
                ]);
            }

            return RewardPurchase::create([
                'user_id' => $user->id,
                'reward_id' => $reward->id,
                'wallet_team_id' => $walletTeam->id,
                'cost_baxx' => $reward->cost_baxx,
                'purchased_at' => now(),
            ]);
        });
    }

    /**
     * Refund a reward purchase (admin action).
     */
    public function refundPurchase(RewardPurchase $purchase, User $admin): void
    {
        if ($purchase->isRefunded()) {
            throw ValidationException::withMessages([
                'purchase' => 'Dieser Kauf wurde bereits erstattet.',
            ]);
        }

        $purchase->update([
            'refunded_at' => now(),
            'refunded_by' => $admin->id,
        ]);
    }

    public function getEarnedBaxx(User $user): int
    {
        return $this->getWalletState($user)['earnedBaxx'];
    }

    /**
     * @throws LogicException
     */
    public function getAvailableBaxx(User $user): int
    {
        $walletState = $this->getWalletState($user);

        if (! is_int($walletState['availableBaxx'])) {
            throw new LogicException($walletState['warning'] ?? 'Das Baxx-Guthaben ist aktuell nicht verfügbar.');
        }

        return $walletState['availableBaxx'];
    }

    /**
     * @throws LogicException
     */
    public function getSpentBaxx(User $user): int
    {
        $walletState = $this->getWalletState($user);

        if (! is_int($walletState['spentBaxx'])) {
            throw new LogicException($walletState['warning'] ?? 'Das Baxx-Guthaben ist aktuell nicht verfügbar.');
        }

        return $walletState['spentBaxx'];
    }

    public function hasUnlockedReward(User $user, string $slug): bool
    {
        $reward = Reward::where('slug', $slug)->first();

        if (! $reward) {
            return false;
        }

        return RewardPurchase::where('user_id', $user->id)
            ->where('reward_id', $reward->id)
            ->active()
            ->exists();
    }

    public function hasUnlockedRewardId(User $user, int $rewardId): bool
    {
        return RewardPurchase::where('user_id', $user->id)
            ->where('reward_id', $rewardId)
            ->active()
            ->exists();
    }

    /**
     * @return array<int>
     */
    public function getUnlockedRewardIds(User $user): array
    {
        return RewardPurchase::where('user_id', $user->id)
            ->active()
            ->pluck('reward_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @throws AuthorizationException
     */
    public function assertRewardUnlocked(string $rewardSlug): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            throw new AuthorizationException('Nicht authentifiziert.');
        }

        if (! $this->hasUnlockedReward($user, $rewardSlug)) {
            throw new AuthorizationException('Du musst diese Belohnung zuerst unter /belohnungen freischalten.');
        }
    }

    /**
     * @return array{
     *     total_spent_baxx: int,
     *     rewards_stats: Collection,
     *     never_purchased_rewards: Collection,
     *     recent_purchases: Collection
     * }
     */
    public function getAdminStatistics(): array
    {
        $totalSpentBaxx = (int) RewardPurchase::active()->sum('cost_baxx');

        $rewardsStats = Reward::query()
            ->withCount(['activePurchases as purchase_count'])
            ->withSum('activePurchases as total_baxx_earned', 'cost_baxx')
            ->orderByDesc('purchase_count')
            ->get();

        $neverPurchased = Reward::query()
            ->whereDoesntHave('activePurchases')
            ->where('is_active', true)
            ->get();

        $recentPurchases = RewardPurchase::with(['user', 'reward', 'refundedByUser'])
            ->latest('purchased_at')
            ->limit(50)
            ->get();

        return [
            'total_spent_baxx' => $totalSpentBaxx,
            'rewards_stats' => $rewardsStats,
            'never_purchased_rewards' => $neverPurchased,
            'recent_purchases' => $recentPurchases,
        ];
    }

    private function assertSpendableWallet(User $user): void
    {
        $walletState = $this->getWalletState($user);

        if ($walletState['status'] === 'ok') {
            return;
        }

        throw ValidationException::withMessages([
            'reward' => $walletState['warning'] ?? 'Das Baxx-Guthaben ist aktuell nicht verfügbar.',
        ]);
    }

    private function getAssignedSpentBaxx(User $user, Team $walletTeam): int
    {
        return (int) RewardPurchase::where('user_id', $user->id)
            ->active()
            ->where('wallet_team_id', $walletTeam->id)
            ->sum('cost_baxx');
    }

    private function resolveRewardWalletTeam(): Team
    {
        $walletTeam = Team::membersTeam();

        if (! $walletTeam) {
            throw new LogicException($this->missingMembersTeamWarning());
        }

        return $walletTeam;
    }

    private function hasAmbiguousLegacyPurchases(User $user): bool
    {
        return RewardPurchase::where('user_id', $user->id)
            ->active()
            ->whereNull('wallet_team_id')
            ->exists();
    }

    private function ambiguousLegacyWarning(): string
    {
        return 'Dein Baxx-Guthaben enthält ältere Käufe ohne eindeutige Wallet-Zuordnung. Bereits freigeschaltete Inhalte bleiben nutzbar, neue Käufe sind aktuell nicht möglich.';
    }

    private function missingMembersTeamWarning(): string
    {
        return 'Das Mitglieder-Team ist derzeit nicht verfügbar. Baxx-Guthaben und neue Freischaltungen können aktuell nicht geladen werden.';
    }
}
