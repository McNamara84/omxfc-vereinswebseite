<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LatestReviewsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_endpoint_returns_five_latest_member_reviews(): void
    {
        $team = Team::factory()->create(['name' => 'Mitglieder']);
        $otherTeam = Team::factory()->create();

        Team::clearMembersTeamCache();
        Cache::forever(Team::MEMBERS_TEAM_CACHE_KEY, $team);
        Cache::forever(Team::MEMBERS_TEAM_ID_CACHE_KEY, $team->id);

        $book = Book::factory()->create([
            'roman_number' => 42,
            'title' => 'Der finale Sturm',
        ]);

        $otherBook = Book::factory()->create([
            'roman_number' => 43,
            'title' => 'Abgeschottete Wege',
        ]);

        // Create six reviews for the members team to ensure only five are returned
        $reviews = collect();
        foreach (range(1, 6) as $offset) {
            $reviews->push(
                Review::factory()->create([
                    'team_id' => $team->id,
                    'book_id' => $book->id,
                    'created_at' => Carbon::now()->subDays($offset),
                ])
            );
        }

        // Review from another team should never appear
        Review::factory()->create([
            'team_id' => $otherTeam->id,
            'book_id' => $otherBook->id,
            'created_at' => Carbon::now()->subHours(2),
        ]);

        $response = $this->getJson('/api/reviews/latest');

        $response->assertOk();
        $response->assertJsonCount(5);

        $latestFive = $reviews->sortByDesc('created_at')->take(5)->values();
        $this->assertSame($latestFive->first()->book->roman_number, $response->json('0.roman_number'));
        $this->assertSame($latestFive->first()->book->title, $response->json('0.roman_title'));
        $this->assertSame($latestFive->first()->title, $response->json('0.review_title'));
    }

    public function test_response_contains_truncated_excerpt(): void
    {
        $team = Team::factory()->create(['name' => 'Mitglieder']);
        $book = Book::factory()->create([
            'roman_number' => 99,
            'title' => 'Der unsichtbare Himmel',
        ]);

        Team::clearMembersTeamCache();
        Cache::forever(Team::MEMBERS_TEAM_CACHE_KEY, $team);
        Cache::forever(Team::MEMBERS_TEAM_ID_CACHE_KEY, $team->id);

        $longContent = 'Dies ist eine besonders ausführliche Rezension über einen spannenden Roman, die weit mehr als fünfundsiebzig Zeichen besitzt.';

        $review = Review::factory()->create([
            'team_id' => $team->id,
            'book_id' => $book->id,
            'content' => $longContent,
        ]);

        $response = $this->getJson('/api/reviews/latest');
        $response->assertOk();

        $excerpt = $response->json('0.excerpt');
        $this->assertTrue(mb_strlen($excerpt) <= 76, 'Excerpt should be limited to 75 characters plus ellipsis.');
        $this->assertStringContainsString(mb_substr($longContent, 0, 10), $excerpt);
        $this->assertStringEndsWith('…', $excerpt);
    }
}
