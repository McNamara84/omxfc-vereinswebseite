<?php

namespace Database\Factories;

use App\Models\MaddraxikonRewardPolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MaddraxikonRewardPolicy> */
class MaddraxikonRewardPolicyFactory extends Factory
{
    protected $model = MaddraxikonRewardPolicy::class;

    public function definition(): array
    {
        return [
            'name' => 'Maddraxikon-Regel '.fake()->unique()->numberBetween(1, 1_000_000),
            'status' => MaddraxikonRewardPolicy::STATUS_DRAFT,
            'effective_from' => now()->addDays(
                fake()->unique()->numberBetween(1, 3650)
            ),
            'edit_sessions_enabled' => true,
            'new_articles_enabled' => true,
            'new_article_minimum_bytes' => 500,
            'new_article_points' => 5,
            'created_by' => User::factory(),
            'published_by' => null,
            'published_at' => null,
        ];
    }
}
