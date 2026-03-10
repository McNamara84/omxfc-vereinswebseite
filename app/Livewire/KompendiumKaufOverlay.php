<?php

namespace App\Livewire;

use App\Models\Reward;
use App\Services\RewardService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class KompendiumKaufOverlay extends Component
{
    public int $rewardId;

    public int $costBaxx;

    public int $availableBaxx;

    public bool $purchased = false;

    public string $errorMessage = '';

    public function mount(int $rewardId, int $costBaxx, int $availableBaxx): void
    {
        $this->rewardId = $rewardId;
        $this->costBaxx = $costBaxx;
        $this->availableBaxx = $availableBaxx;
    }

    public function purchase(RewardService $rewardService): void
    {
        $this->errorMessage = '';

        $user = Auth::user();
        $reward = Reward::find($this->rewardId);

        if (! $reward) {
            $this->errorMessage = 'Belohnung nicht gefunden.';

            return;
        }

        try {
            $rewardService->purchaseReward($user, $reward);
            $this->purchased = true;
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->errorMessage = collect($e->errors())->flatten()->first();
        }
    }

    public function render()
    {
        return view('livewire.kompendium-kauf-overlay');
    }
}
