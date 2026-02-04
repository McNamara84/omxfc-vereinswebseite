<?php

namespace Database\Factories;

use App\Models\Fanfiction;
use App\Models\FanfictionComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FanfictionComment>
 */
class FanfictionCommentFactory extends Factory
{
    protected $model = FanfictionComment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fanfiction_id' => Fanfiction::factory(),
            'user_id' => User::factory(),
            'parent_id' => null,
            'content' => $this->faker->paragraph(),
        ];
    }

    /**
     * Indicate that the comment is a reply to another comment.
     */
    public function reply(?FanfictionComment $parent = null): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent?->id ?? FanfictionComment::factory(),
            'fanfiction_id' => $parent?->fanfiction_id ?? $attributes['fanfiction_id'],
        ]);
    }
}
