<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\DefaultAdminAndTeamSeeder;
use Database\Seeders\ReviewsPlaywrightSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewsPlaywrightSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_books_and_reviews(): void
    {
        if (! Team::membersTeam()) {
            $this->seed(DefaultAdminAndTeamSeeder::class);
        }
        $this->seed(ReviewsPlaywrightSeeder::class);

        $team = Team::membersTeam();
        $this->assertNotNull($team);

        $this->assertDatabaseHas('books', [
            'title' => 'Einstieg ins Maddraxiversum',
        ]);

        $this->assertDatabaseHas('books', [
            'title' => 'Mission Mars – Auftakt',
        ]);

        $this->assertDatabaseHas('books', [
            'title' => 'Maddrax Hardcover – Sammlerausgabe',
        ]);

        $review = Review::where('title', 'Admin Sicht auf den Auftakt')->first();
        $this->assertNotNull($review);

        $reviewer = User::firstWhere('email', 'playwright-reviewer@example.com');
        $this->assertNotNull($reviewer);
        $this->assertTrue($team?->users()->where('users.id', $reviewer->id)->exists());
    }
}
