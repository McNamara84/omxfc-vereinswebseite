<?php

namespace Database\Factories;

use App\Models\MaddraxikonRewardPolicy;
use App\Models\MaddraxikonRewardPolicyTier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MaddraxikonRewardPolicyTier> */
class MaddraxikonRewardPolicyTierFactory extends Factory
{
    protected $model = MaddraxikonRewardPolicyTier::class;

    public function definition(): array
    {
        return [
            'maddraxikon_reward_policy_id' => MaddraxikonRewardPolicy::factory(),
            'minimum_added_bytes' => fake()->unique()->numberBetween(1, 1_000_000),
            'points' => fake()->numberBetween(1, 10),
        ];
    }
}
