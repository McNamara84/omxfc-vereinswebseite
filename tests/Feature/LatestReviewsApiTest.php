<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('endpoint returns five latest member reviews', function (): void {
    $team = Team::factory()->create(['name' => 'Mitglieder']);
    $otherTeam = Team::factory()->create();
    Team::clearMembersTeamCache();
    Cache::forever(Team::MEMBERS_TEAM_ID_CACHE_KEY, $team->id);
    $book = Book::factory()->create(['roman_number' => 42, 'title' => 'Der finale Sturm']);
    $otherBook = Book::factory()->create(['roman_number' => 43, 'title' => 'Abgeschottete Wege']);
    // Create six reviews for the members team to ensure only five are returned
    $reviews = collect();
    foreach (range(1, 6) as $offset) {
        $reviews->push(Review::factory()->create(['team_id' => $team->id, 'book_id' => $book->id, 'created_at' => Carbon::now()->subDays($offset)]));
    }
    // Review from another team should never appear
    Review::factory()->create(['team_id' => $otherTeam->id, 'book_id' => $otherBook->id, 'created_at' => Carbon::now()->subHours(2)]);
    $response = $this->getJson(route('api.reviews.latest'));
    $response->assertOk();
    $response->assertJsonCount(5);
    $latestFive = $reviews->sortByDesc('created_at')->take(5)->values();
    expect($response->json('0.roman_number'))->toBe($latestFive->first()->book->roman_number)
        ->and($response->json('0.roman_title'))->toBe($latestFive->first()->book->title)
        ->and($response->json('0.review_title'))->toBe($latestFive->first()->title)
        ->and($response->json('0.reviewed_at'))->toBe($latestFive->first()->created_at->toISOString());
});

test('response contains truncated excerpt', function (): void {
    $team = Team::factory()->create(['name' => 'Mitglieder']);
    $book = Book::factory()->create(['roman_number' => 99, 'title' => 'Der unsichtbare Himmel']);
    Team::clearMembersTeamCache();
    Cache::forever(Team::MEMBERS_TEAM_ID_CACHE_KEY, $team->id);
    $longContent = 'Dies ist eine besonders ausführliche Rezension über einen spannenden Roman, die weit mehr als fünfundsiebzig Zeichen besitzt.';
    $review = Review::factory()->create(['team_id' => $team->id, 'book_id' => $book->id, 'content' => $longContent]);
    $response = $this->getJson(route('api.reviews.latest'));
    $response->assertOk();
    $excerpt = $response->json('0.excerpt');
    expect(mb_strlen($excerpt))->toBeLessThanOrEqual(75)
        ->and(mb_strlen(rtrim($excerpt, '…')))->toBeLessThanOrEqual(74)
        ->and($excerpt)->toContain(mb_substr($longContent, 0, 10))
        ->toEndWith('…');
});

test('response contains reviewed at timestamp', function (): void {
    $team = Team::factory()->create(['name' => 'Mitglieder']);
    $book = Book::factory()->create(['roman_number' => 21, 'title' => 'Zeitschatten']);
    Team::clearMembersTeamCache();
    Cache::forever(Team::MEMBERS_TEAM_ID_CACHE_KEY, $team->id);
    $reviewDate = Carbon::create(2024, 5, 1, 8, 15, 0, config('app.timezone'));
    Review::factory()->create(['team_id' => $team->id, 'book_id' => $book->id, 'created_at' => $reviewDate]);
    $response = $this->getJson(route('api.reviews.latest'));
    $response->assertOk();
    expect($response->json('0.reviewed_at'))->toBe($reviewDate->toISOString());
});

test('latest reviews preview is cached per update timestamp', function (): void {
    $team = Team::factory()->create(['name' => 'Mitglieder']);
    $book = Book::factory()->create(['roman_number' => 100, 'title' => 'Zeitsprung ins All']);
    Team::clearMembersTeamCache();
    Cache::forever(Team::MEMBERS_TEAM_ID_CACHE_KEY, $team->id);
    $review = Review::factory()->create(['team_id' => $team->id, 'book_id' => $book->id, 'content' => 'Erste Version des Textes mit markanter Einleitung.']);
    Carbon::setTestNow(Carbon::now());
    $firstResponse = $this->getJson(route('api.reviews.latest'));
    $firstExcerpt = $firstResponse->json('0.excerpt');
    expect($firstExcerpt)->toContain('Erste Version');
    Carbon::setTestNow(Carbon::now()->addSecond());
    $review->update(['content' => 'Aktualisierte Version der Rezension mit neuer Einleitung und Details.']);
    $secondResponse = $this->getJson(route('api.reviews.latest'));
    $secondExcerpt = $secondResponse->json('0.excerpt');
    expect($secondExcerpt)->toContain('Aktualisierte Version')->not->toContain('Erste Version');
});

test('latest reviews endpoint is rate limited', function (): void {
    $route = Route::getRoutes()->match(Request::create('/api/reviews/latest'));
    expect($route->gatherMiddleware())->toContain('throttle:60,1');
});
