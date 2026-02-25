<?php

namespace Database\Factories;

use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RewardPurchase>
 */
class RewardPurchaseFactory extends Factory
{
    protected $model = RewardPurchase::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reward_id' => Reward::factory(),
            'cost_baxx' => $this->faker->numberBetween(1, 100),
            'purchased_at' => now(),
            'refunded_at' => null,
            'refunded_by' => null,
        ];
    }

    /**
     * Indicate that the purchase has been refunded.
     */
    public function refunded(?User $admin = null): static
    {
        return $this->state(fn (array $attributes) => [
            'refunded_at' => now(),
            'refunded_by' => $admin?->id ?? User::factory(),
        ]);
    }
}
