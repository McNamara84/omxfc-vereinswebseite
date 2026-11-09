<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Book;
use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonReviewRating;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUserWithRole;

trait MaddraxikonReviewRatingUiTestFixtures
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    /**
     * @return array{Review, Book, MaddraxikonAccountLink, MaddraxikonReviewRating}
     */
    private function createReviewWithRating(int $rating): array
    {
        $user = $this->actingMember();
        $book = Book::factory()->mapped(100, 'MX 100 – Testroman')->create();
        $review = Review::factory()->create(['team_id' => $user->currentTeam->id, 'user_id' => $user->id, 'book_id' => $book->id, 'title' => 'Rezension mit Kometen']);
        $link = MaddraxikonAccountLink::factory()->for($user)->create(['wiki_user_id' => 42]);
        $snapshot = MaddraxikonReviewRating::query()->create(['review_id' => $review->id, 'book_id' => $book->id, 'user_id' => $user->id, 'account_link_id' => $link->id, 'maddraxikon_page_id' => $book->maddraxikon_page_id, 'wiki_user_id' => $link->wiki_user_id, 'rating' => $rating, 'source_voted_at' => now()->subHour(), 'synced_at' => now()]);

        return [$review, $book, $link, $snapshot];
    }
}
