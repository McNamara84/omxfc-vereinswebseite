<?php

namespace App\Livewire;

use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Services\ReviewBaxxService;
use App\Services\RewardService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class BelohnungenIndex extends Component
{
    #[Url(except: 'alle')]
    public string $filter = 'alle';

    #[Url(except: 'alle')]
    public string $categoryFilter = 'alle';

    public function mount(): void
    {
        // Nothing to mount — computed properties handle data loading.
    }

    #[Computed]
    public function walletState(): array
    {
        return app(RewardService::class)->getWalletState(Auth::user());
    }

    #[Computed]
    public function availableBaxx(): int
    {
        return $this->walletState['availableBaxx'] ?? 0;
    }

    #[Computed]
    public function earnedBaxx(): int
    {
        return $this->walletState['earnedBaxx'];
    }

    #[Computed]
    public function spentBaxx(): int
    {
        return $this->walletState['spentBaxx'] ?? 0;
    }

    #[Computed]
    public function walletWarning(): ?string
    {
        return $this->walletState['warning'];
    }

    #[Computed]
    public function hasAvailableWallet(): bool
    {
        return is_int($this->walletState['availableBaxx']);
    }

    #[Computed]
    public function rewards(): array
    {
        $user = Auth::user();
        $purchasedRewardIds = RewardPurchase::where('user_id', $user->id)
            ->active()
            ->pluck('reward_id')
            ->toArray();
        $walletAvailableBaxx = $this->hasAvailableWallet ? $this->availableBaxx : null;

        $query = Reward::active()->orderBy('sort_order')->orderBy('cost_baxx');

        if ($this->categoryFilter !== 'alle') {
            $query->byCategory($this->categoryFilter);
        }

        $rewards = $query->get()->map(function (Reward $reward) use ($purchasedRewardIds, $walletAvailableBaxx) {
            $purchased = in_array($reward->id, $purchasedRewardIds);
            $canAfford = $walletAvailableBaxx !== null && $walletAvailableBaxx >= $reward->cost_baxx;
            $missingBaxx = $walletAvailableBaxx !== null && ! $canAfford
                ? $reward->cost_baxx - $walletAvailableBaxx
                : null;

            return [
                'id' => $reward->id,
                'title' => $reward->title,
                'description' => $reward->description,
                'category' => $reward->category,
                'cost_baxx' => $reward->cost_baxx,
                'purchased' => $purchased,
                'can_afford' => $canAfford,
                'wallet_unavailable' => $walletAvailableBaxx === null,
                'missing_baxx' => $missingBaxx,
            ];
        });

        // Apply status filter
        if ($this->filter === 'freigeschaltet') {
            $rewards = $rewards->where('purchased', true);
        } elseif ($this->filter === 'nicht_freigeschaltet') {
            $rewards = $rewards->where('purchased', false);
        }

        return $rewards->groupBy('category')->toArray();
    }

    #[Computed]
    public function categories(): array
    {
        return Reward::active()
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->toArray();
    }

    #[Computed]
    public function reviewRewardConfiguration(): array
    {
        return app(ReviewBaxxService::class)->getMemberConfiguration();
    }

    public function purchase(int $rewardId): void
    {
        $reward = Reward::findOrFail($rewardId);
        $service = app(RewardService::class);

        try {
            $service->purchaseReward(Auth::user(), $reward);
            $this->dispatch('toast', type: 'success', title: e($reward->title).' freigeschaltet!', description: "{$reward->cost_baxx} Baxx wurden abgezogen.");
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first();
            $this->dispatch('toast', type: 'error', title: 'Fehler', description: $message);
        }

        // Invalidate computed caches (earnedBaxx bleibt – ein Kauf ändert nicht die verdienten Baxx)
        unset($this->walletState, $this->availableBaxx, $this->spentBaxx, $this->walletWarning, $this->hasAvailableWallet, $this->rewards);
    }

    public function updatedFilter(): void
    {
        unset($this->rewards);
    }

    public function updatedCategoryFilter(): void
    {
        unset($this->rewards);
    }

    public function placeholder()
    {
        return view('components.skeleton-card', ['cols' => 3, 'rows' => 2]);
    }

    public function render()
    {
        return view('livewire.belohnungen-index')
            ->layout('layouts.app', ['title' => 'Belohnungen']);
    }
}
