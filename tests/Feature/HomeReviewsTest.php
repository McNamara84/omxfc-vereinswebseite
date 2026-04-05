<?php

namespace Tests\Feature;

use App\Livewire\HomeReviews;
use App\Models\Book;
use App\Models\Review;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HomeReviewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_component_renders_on_homepage(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSeeLivewire(HomeReviews::class);
    }

    public function test_shows_latest_reviews(): void
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create();
        $book = Book::factory()->create(['title' => 'Der Untergang', 'roman_number' => 42]);

        Review::factory()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'book_id' => $book->id,
            'title' => 'Spannende Rezension',
            'content' => 'Sehr guter Roman mit viel Action.',
        ]);

        Livewire::test(HomeReviews::class)
            ->assertSee('Spannende Rezension')
            ->assertSee('Der Untergang');
    }

    public function test_shows_max_five_reviews(): void
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create();

        for ($i = 1; $i <= 7; $i++) {
            $book = Book::factory()->create(['roman_number' => 100 + $i]);
            Review::factory()->create([
                'team_id' => $team->id,
                'user_id' => $user->id,
                'book_id' => $book->id,
                'title' => "Rezension Nummer $i",
            ]);
        }

        // The 6th and 7th review titles should not appear (only latest 5 shown)
        $component = Livewire::test(HomeReviews::class);
        $html = $component->html();
        $this->assertLessThanOrEqual(5, substr_count($html, 'Rezension Nummer'));
    }

    public function test_empty_state_when_no_reviews(): void
    {
        Livewire::test(HomeReviews::class)
            ->assertSee('Derzeit liegen keine Rezensionen vor');
    }

    public function test_soft_deleted_reviews_are_excluded(): void
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create();
        $book = Book::factory()->create(['roman_number' => 999]);

        $review = Review::factory()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'book_id' => $book->id,
            'title' => 'Gelöschte Rezension',
        ]);
        $review->delete();

        Livewire::test(HomeReviews::class)
            ->assertDontSee('Gelöschte Rezension');
    }
}
