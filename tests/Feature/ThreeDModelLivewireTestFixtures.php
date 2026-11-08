<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Models\Team;
use App\Models\ThreeDModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesUserWithRole;

trait ThreeDModelLivewireTestFixtures
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    // ── Hilfsmethoden ─────────────────────────────────────────
    private function createModelWithReward(int $costBaxx = 10, array $attributes = []): ThreeDModel
    {
        $model = ThreeDModel::factory()->create($attributes);
        $reward = Reward::create(['title' => $model->name, 'slug' => '3d-'.Str::slug($model->name).'-'.$model->id, 'description' => $model->description ?? '', 'category' => '3D-Modelle', 'cost_baxx' => $costBaxx, 'is_active' => true, 'sort_order' => 0]);
        $model->update(['reward_id' => $reward->id]);

        return $model->refresh();
    }

    private function purchaseModelForUser(ThreeDModel $model, User $user): RewardPurchase
    {
        return RewardPurchase::create(['user_id' => $user->id, 'reward_id' => $model->reward_id, 'wallet_team_id' => Team::membersTeam()?->id, 'cost_baxx' => $model->reward->cost_baxx, 'purchased_at' => now()]);
    }
}
