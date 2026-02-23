<?php

namespace Database\Factories;

use App\Models\Reward;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Reward>
 */
class RewardFactory extends Factory
{
    protected $model = Reward::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = $this->faker->unique()->sentence(3);

        return [
            'title' => $title,
            'description' => $this->faker->sentence(),
            'category' => $this->faker->randomElement(['Statistiken', 'Downloads', 'Maddraxiversum', 'Kompendium', 'Allgemein']),
            'slug' => Str::slug($title),
            'cost_baxx' => $this->faker->numberBetween(1, 100),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * Indicate that the reward is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
