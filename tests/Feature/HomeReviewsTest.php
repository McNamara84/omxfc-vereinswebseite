<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\HomeReviews;
use App\Models\Book;
use App\Models\Review;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('component renders on homepage', function (): void {
    $response = $this->get('/');
    $response->assertOk();
    $response->assertSeeLivewire(HomeReviews::class);
});

test('shows latest reviews', function (): void {
    $team = Team::query()->where('name', 'Mitglieder')->first();
    $user = User::factory()->create();
    $book = Book::factory()->create(['title' => 'Der Untergang', 'roman_number' => 42]);
    Review::factory()->create(['team_id' => $team->id, 'user_id' => $user->id, 'book_id' => $book->id, 'title' => 'Spannende Rezension', 'content' => 'Sehr guter Roman mit viel Action.']);
    Livewire::test(HomeReviews::class)->assertSee('Spannende Rezension')->assertSee('Der Untergang');
});

test('shows max five reviews', function (): void {
    $team = Team::query()->where('name', 'Mitglieder')->first();
    $user = User::factory()->create();
    for ($i = 1; $i <= 7; $i++) {
        $book = Book::factory()->create(['roman_number' => 100 + $i]);
        Review::factory()->create(['team_id' => $team->id, 'user_id' => $user->id, 'book_id' => $book->id, 'title' => "Rezension Nummer {$i}"]);
    }
    // The 6th and 7th review titles should not appear (only latest 5 shown)
    $component = Livewire::test(HomeReviews::class);
    $html = $component->html();
    expect(substr_count($html, 'Rezension Nummer'))->toBeLessThanOrEqual(5);
});

test('empty state when no reviews', function (): void {
    Livewire::test(HomeReviews::class)->assertSee('Derzeit liegen keine Rezensionen vor');
});

test('soft deleted reviews are excluded', function (): void {
    $team = Team::query()->where('name', 'Mitglieder')->first();
    $user = User::factory()->create();
    $book = Book::factory()->create(['roman_number' => 999]);
    $review = Review::factory()->create(['team_id' => $team->id, 'user_id' => $user->id, 'book_id' => $book->id, 'title' => 'Gelöschte Rezension']);
    $review->delete();
    Livewire::test(HomeReviews::class)->assertDontSee('Gelöschte Rezension');
});

test('reviews are cached as array payloads for five minutes', function (): void {
    $team = Team::query()->where('name', 'Mitglieder')->first();
    $user = User::factory()->create();
    $book = Book::factory()->create(['title' => 'Cache Roman', 'roman_number' => 77]);
    Review::factory()->create(['team_id' => $team->id, 'user_id' => $user->id, 'book_id' => $book->id, 'title' => 'Cache Rezension', 'content' => 'Cache-sichere Ausgabe der neuesten Rezensionen.']);
    Livewire::test(HomeReviews::class)->assertSee('Cache Rezension');
    $cachedReviews = Cache::get(HomeReviews::cacheKeyForTeam($team->id));
    expect($cachedReviews)->toBeArray()
        ->toHaveCount(1)
        ->and($cachedReviews[0]['review_title'])->toBe('Cache Rezension')
        ->and($cachedReviews[0]['roman_title'])->toBe('Cache Roman')
        ->and($cachedReviews[0]['reviewed_at_iso'])->toBeString()
        ->and($cachedReviews[0]['reviewed_at_label'])->toBeString();
});
