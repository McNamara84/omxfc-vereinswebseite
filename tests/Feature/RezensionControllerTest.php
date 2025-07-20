<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;
use App\Models\Book;
use App\Notifications\NewReviewNotification;
use Illuminate\Support\Facades\Mail;

class RezensionControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingMember(string $role = 'Mitglied'): User
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role]);
        return $user;
    }

    public function test_store_creates_review_and_notifies_author(): void
    {
        Mail::fake();

        $author = User::factory()->create(['notify_new_review' => true]);
        $book = Book::create([
            'roman_number' => 1,
            'title' => 'Roman1',
            'author' => $author->name,
        ]);

        $user = $this->actingMember();
        $this->actingAs($user);

        $response = $this->post(route('reviews.store', $book), [
            'title' => 'Tolle Rezension',
            'content' => str_repeat('A', 140),
        ]);

        $response->assertRedirect(route('reviews.show', $book, false));
        $this->assertDatabaseHas('reviews', [
            'book_id' => $book->id,
            'title' => 'Tolle Rezension',
        ]);
        Mail::assertSent(NewReviewNotification::class);
    }
}
