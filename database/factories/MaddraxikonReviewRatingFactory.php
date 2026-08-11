<?php

namespace Database\Factories;

use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonReviewRating;
use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaddraxikonReviewRating>
 */
class MaddraxikonReviewRatingFactory extends Factory
{
    protected $model = MaddraxikonReviewRating::class;

    public function definition(): array
    {
        return [
            'review_id' => Review::factory(),
            'book_id' => fn (array $attributes): int => Review::findOrFail($attributes['review_id'])->book_id,
            'user_id' => fn (array $attributes): int => Review::findOrFail($attributes['review_id'])->user_id,
            'account_link_id' => function (array $attributes): int {
                return MaddraxikonAccountLink::factory()->create([
                    'user_id' => $attributes['user_id'],
                ])->id;
            },
            'maddraxikon_page_id' => function (array $attributes): int {
                $review = Review::findOrFail($attributes['review_id']);
                $pageId = $review->book->maddraxikon_page_id
                    ?? fake()->unique()->numberBetween(1, 2_000_000_000);

                if ($review->book->maddraxikon_page_id === null) {
                    $review->book->update([
                        'maddraxikon_page_id' => $pageId,
                        'maddraxikon_page_title' => 'Testroman '.$pageId,
                        'maddraxikon_page_verified_at' => now(),
                    ]);
                }

                return $pageId;
            },
            'wiki_user_id' => fn (array $attributes): int => MaddraxikonAccountLink::findOrFail(
                $attributes['account_link_id']
            )->wiki_user_id,
            'rating' => fake()->numberBetween(1, 5),
            'source_voted_at' => now()->subHour(),
            'synced_at' => now(),
        ];
    }
}
