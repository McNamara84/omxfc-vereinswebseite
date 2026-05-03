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

        $walletTeam = $this->resolveRewardWalletTeam();

        return DB::transaction(function () use ($user, $reward, $walletTeam) {
            // Lock ALL purchases of this user to prevent concurrent double-spending.
            // This ensures getSpentBaxx sees a consistent snapshot and no two
            // concurrent requests can both pass the balance check.
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
     * The user gets their Baxx back automatically since available Baxx is calculated.
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

    /**
     * Get the earned Baxx for the reward wallet.
     *
     * Reward unlocks belong to the Mitgliederbereich and therefore use the
     * Mitglieder team as the canonical Baxx wallet, independent of the
     * currently selected Jetstream team.
     */
    public function getEarnedBaxx(User $user): int
    {
        return $this->teamPointService->getUserPointsForTeam($user, $this->resolveRewardWalletTeam());
    }

    /**
     * Get the available (spendable) Baxx for the reward wallet.
     * Available = Earned - Spent (on active, non-refunded purchases).
     */
    public function getAvailableBaxx(User $user): int
    {
        $earnedBaxx = $this->getEarnedBaxx($user);
        $spentBaxx = $this->getSpentBaxx($user);

        return max(0, $earnedBaxx - $spentBaxx);
    }

    /**
     * Get the total Baxx spent by a user on active purchases.
     * Reward purchases unlock account-wide members-area features, so spending
     * is tracked globally per user rather than per team.
     */
    public function getSpentBaxx(User $user): int
    {
        $walletTeam = $this->resolveRewardWalletTeam();

        if ($this->hasAmbiguousLegacyPurchases($user, $walletTeam)) {
            throw new LogicException(
                'Für diesen Benutzer existieren ältere Belohnungskäufe ohne eindeutige Wallet-Zuordnung. '
                .'Bitte diese Käufe vor der weiteren Verwendung des Baxx-Guthabens prüfen.'
            );
        }

        $canCountLegacyPurchases = ! $this->userHasAdditionalNonPersonalTeams($user, $walletTeam);

        return (int) RewardPurchase::where('user_id', $user->id)
            ->active()
            ->where(function ($query) use ($walletTeam, $canCountLegacyPurchases) {
                $query->where('wallet_team_id', $walletTeam->id);

                if ($canCountLegacyPurchases) {
                    $query->orWhereNull('wallet_team_id');
                }
            })
            ->sum('cost_baxx');
    }

    private function resolveRewardWalletTeam(): Team
    {
        $walletTeam = Team::membersTeam();

        if (! $walletTeam) {
            throw new LogicException('Das Mitglieder-Team existiert nicht. Das Baxx-Guthaben kann nicht berechnet werden.');
        }

        return $walletTeam;
    }

    private function hasAmbiguousLegacyPurchases(User $user, Team $walletTeam): bool
    {
        if (! $this->userHasAdditionalNonPersonalTeams($user, $walletTeam)) {
            return false;
        }

        return RewardPurchase::where('user_id', $user->id)
            ->active()
            ->whereNull('wallet_team_id')
            ->exists();
    }

    private function userHasAdditionalNonPersonalTeams(User $user, Team $walletTeam): bool
    {
        return $user->teams()
            ->where('teams.personal_team', false)
            ->where('teams.id', '!=', $walletTeam->id)
            ->exists();
    }

    /**
     * Check if a user has unlocked a specific reward by slug.
     */
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

    /**
     * Check if a user has unlocked a specific reward by ID.
     *
     * More efficient than hasUnlockedReward() when the Reward model is already loaded,
     * since it skips the extra slug-based lookup.
     */
    public function hasUnlockedRewardId(User $user, int $rewardId): bool
    {
        return RewardPurchase::where('user_id', $user->id)
            ->where('reward_id', $rewardId)
            ->active()
            ->exists();
    }

    /**
     * Get all reward IDs the user has actively purchased (single query).
     *
     * Useful in list views to avoid N+1 queries when checking unlock status
     * for multiple rewards at once.
     *
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
     * Ensure the authenticated user has unlocked a reward by slug.
     *
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
     * Get admin statistics for the rewards system.
     *
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
}
