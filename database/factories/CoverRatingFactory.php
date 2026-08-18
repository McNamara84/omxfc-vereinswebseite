<?php

namespace Database\Factories;

use App\Models\BookCover;
use App\Models\CoverRating;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CoverRating> */
class CoverRatingFactory extends Factory
{
    protected $model = CoverRating::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'book_cover_id' => BookCover::factory(),
            'rating' => fake()->numberBetween(1, 5),
        ];
    }
}
