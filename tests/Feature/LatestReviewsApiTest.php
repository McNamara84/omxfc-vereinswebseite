<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
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
        $this->assertTrue(mb_strlen($excerpt) <= 75, 'Excerpt should be limited to 75 characters (including ellipsis).');
        $this->assertStringContainsString(mb_substr($longContent, 0, 10), $excerpt);
        $this->assertStringEndsWith('…', $excerpt);
    }

    public function test_latest_reviews_preview_is_cached_per_update_timestamp(): void
    {
        $team = Team::factory()->create(['name' => 'Mitglieder']);
        $book = Book::factory()->create([
            'roman_number' => 100,
            'title' => 'Zeitsprung ins All',
        ]);

        Team::clearMembersTeamCache();
        Cache::forever(Team::MEMBERS_TEAM_CACHE_KEY, $team);
        Cache::forever(Team::MEMBERS_TEAM_ID_CACHE_KEY, $team->id);

        $review = Review::factory()->create([
            'team_id' => $team->id,
            'book_id' => $book->id,
            'content' => 'Erste Version des Textes mit markanter Einleitung.',
        ]);

        $firstResponse = $this->getJson('/api/reviews/latest');
        $firstExcerpt = $firstResponse->json('0.excerpt');
        $this->assertStringContainsString('Erste Version', $firstExcerpt);

        sleep(1);

        $review->update([
            'content' => 'Aktualisierte Version der Rezension mit neuer Einleitung und Details.',
        ]);

        $secondResponse = $this->getJson('/api/reviews/latest');
        $secondExcerpt = $secondResponse->json('0.excerpt');

        $this->assertStringContainsString('Aktualisierte Version', $secondExcerpt);
        $this->assertStringNotContainsString('Erste Version', $secondExcerpt);
    }

    public function test_latest_reviews_endpoint_is_rate_limited(): void
    {
        $route = Route::getRoutes()
            ->match(Request::create('/api/reviews/latest'));

        $this->assertContains('throttle:60,1', $route->gatherMiddleware());
    }
}
