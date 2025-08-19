<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;
use App\Models\Book;
use Illuminate\Support\Facades\Mail;
use App\Models\Review;
use App\Enums\BookType;

class ReviewCreationTest extends TestCase
{
    use RefreshDatabase;

    private function actingMember(): User
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => 'Mitglied']);
        return $user;
    }

    public function test_member_can_store_review(): void
    {
        Mail::fake();

        $book = Book::create([
            'roman_number' => 1,
            'title' => 'Roman1',
            'author' => 'Author One',
        ]);

        $user = $this->actingMember();
        $this->actingAs($user);

        $response = $this->post("/rezensionen/{$book->id}", [
            'title' => 'Tolles Buch',
            'content' => str_repeat('A', 150),
        ]);

        $response->assertRedirect(route('reviews.show', $book));
        $this->assertDatabaseHas('reviews', [
            'book_id' => $book->id,
            'user_id' => $user->id,
            'title' => 'Tolles Buch',
        ]);

        Mail::assertNothingSent();
    }

    public function test_member_can_store_hardcover_review(): void
    {
        Mail::fake();

        $book = Book::create([
            'roman_number' => 1,
            'title' => 'Hardcover1',
            'author' => 'Author One',
            'type' => BookType::MaddraxHardcover,
        ]);

        $user = $this->actingMember();
        $this->actingAs($user);

        $response = $this->post("/rezensionen/{$book->id}", [
            'title' => 'Tolles Hardcover',
            'content' => str_repeat('A', 150),
        ]);

        $response->assertRedirect(route('reviews.show', $book));
        $this->assertDatabaseHas('reviews', [
            'book_id' => $book->id,
            'user_id' => $user->id,
            'title' => 'Tolles Hardcover',
        ]);

        Mail::assertNothingSent();
    }

    public function test_point_awarded_on_every_tenth_review(): void
    {
        Mail::fake();

        $user = $this->actingMember();
        $this->actingAs($user);

        // create nine existing reviews for the user
        for ($i = 1; $i <= 9; $i++) {
            $book = Book::create([
                'roman_number' => $i,
                'title' => 'Roman'.$i,
                'author' => 'Author',
            ]);

            Review::create([
                'team_id' => $user->currentTeam->id,
                'user_id' => $user->id,
                'book_id' => $book->id,
                'title' => 'Review'.$i,
                'content' => str_repeat('A', 150),
            ]);
        }

        $newBook = Book::create([
            'roman_number' => 10,
            'title' => 'Roman10',
            'author' => 'Author',
        ]);

        $this->post("/rezensionen/{$newBook->id}", [
            'title' => 'Tolles Buch',
            'content' => str_repeat('A', 150),
        ]);

        $this->assertDatabaseCount('user_points', 1);
        $this->assertDatabaseHas('user_points', [
            'user_id' => $user->id,
            'points' => 1,
        ]);
    }

    public function test_non_member_cannot_store_review(): void
    {
        $book = Book::create([
            'roman_number' => 2,
            'title' => 'Roman2',
            'author' => 'Author Two',
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post("/rezensionen/{$book->id}", [
            'title' => 'Test',
            'content' => str_repeat('B', 150),
        ])->assertForbidden();
    }
}
