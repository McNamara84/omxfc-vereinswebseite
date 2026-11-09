<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Reward;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPoint;
use App\Services\RewardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUserWithRole;

trait MitgliederKarteFeatureTestFixtures
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    private function purchaseMemberMapReward(User $user): void
    {
        $reward = Reward::query()->where('slug', 'mitgliederkarte')->firstOrFail();
        $membersTeam = Team::membersTeam();
        expect($membersTeam)->not->toBeNull();
        if (! $membersTeam->users()->whereKey($user->id)->exists()) {
            $membersTeam->users()->attach($user, ['role' => Role::Mitglied->value]);
        }
        UserPoint::query()->create(['user_id' => $user->id, 'team_id' => $membersTeam->id, 'points' => $reward->cost_baxx]);
        app(RewardService::class)->purchaseReward($user, $reward);
    }
}
