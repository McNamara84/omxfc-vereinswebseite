<?php

namespace App\Livewire;

use App\Models\Reward;
use App\Services\RewardService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class KompendiumKaufOverlay extends Component
{
    #[Locked]
    public int $rewardId;

    public bool $purchased = false;

    public string $errorMessage = '';

    public function mount(int $rewardId): void
    {
        $this->rewardId = $rewardId;
    }

    #[Computed]
    public function costBaxx(): int
    {
        return Reward::find($this->rewardId)?->cost_baxx ?? 0;
    }

    #[Computed]
    public function availableBaxx(): int
    {
        return app(RewardService::class)->getAvailableBaxx(Auth::user());
    }

    public function purchase(RewardService $rewardService): void
    {
        $this->errorMessage = '';

        $user = Auth::user();

        if (! $user) {
            $this->errorMessage = 'Deine Sitzung ist abgelaufen. Bitte lade die Seite neu und melde dich erneut an.';

            return;
        }

        $reward = Reward::find($this->rewardId);

        if (! $reward) {
            $this->errorMessage = 'Belohnung nicht gefunden.';

            return;
        }

        try {
            $rewardService->purchaseReward($user, $reward);
            $this->purchased = true;
        } catch (ValidationException $e) {
            $this->errorMessage = collect($e->errors())->flatten()->first();
        }
    }

    public function render()
    {
        return view('livewire.kompendium-kauf-overlay');
    }
}
