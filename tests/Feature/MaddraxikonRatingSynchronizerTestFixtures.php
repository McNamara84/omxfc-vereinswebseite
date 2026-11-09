<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Book;
use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonReviewRating;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait MaddraxikonRatingSynchronizerTestFixtures
{
    use RefreshDatabase;

    /**
     * @return array{Review, Book, MaddraxikonAccountLink}
     */
    private function createCandidate(): array
    {
        $user = User::factory()->create();
        $book = Book::factory()->mapped(100, 'MX 100 – Testroman')->create();
        $review = Review::factory()->for($user)->for($book)->create();
        $link = MaddraxikonAccountLink::factory()->for($user)->create(['wiki_key' => 'test-wiki', 'wiki_user_id' => 42]);

        return [$review, $book, $link];
    }

    private function createSnapshot(Review $review, Book $book, MaddraxikonAccountLink $link, int $rating = 4): MaddraxikonReviewRating
    {
        return MaddraxikonReviewRating::query()->create(['review_id' => $review->id, 'book_id' => $book->id, 'user_id' => $review->user_id, 'account_link_id' => $link->id, 'maddraxikon_page_id' => $book->maddraxikon_page_id, 'wiki_user_id' => $link->wiki_user_id, 'rating' => $rating, 'source_voted_at' => now()->subHour(), 'synced_at' => now()]);
    }
}
