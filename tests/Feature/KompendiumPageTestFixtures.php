<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUserWithRole;

trait KompendiumPageTestFixtures
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    private function purchaseKompendiumForUser(User $user): void
    {
        $reward = Reward::where('slug', 'kompendium')->firstOrFail();
        RewardPurchase::create(['user_id' => $user->id, 'reward_id' => $reward->id, 'cost_baxx' => $reward->cost_baxx, 'purchased_at' => now()]);
    }
}
