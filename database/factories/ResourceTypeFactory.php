<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ResourceType>
 */
class ResourceTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application' => 'ELMO',
            'slug' => Str::slug($this->faker->unique()->words(3, true)),
            'name' => ucfirst($this->faker->unique()->words(3, true)),
            'description' => $this->faker->sentence(),
        ];
    }
}
