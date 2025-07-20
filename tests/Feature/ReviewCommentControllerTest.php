<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;
use App\Models\Book;
use App\Models\Review;
use App\Notifications\ReviewCommentNotification;
use Illuminate\Support\Facades\Mail;

class ReviewCommentControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingMember(string $role = 'Mitglied'): User
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role]);
        return $user;
    }

    public function test_comment_saves_and_notifies_author(): void
    {
        Mail::fake();

        $team = Team::where('name', 'Mitglieder')->first();
        $author = User::factory()->create(['current_team_id' => $team->id, 'notify_new_review' => true]);
        $team->users()->attach($author, ['role' => 'Mitglied']);
        $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Foo']);
        $review = Review::create([
            'team_id' => $team->id,
            'user_id' => $author->id,
            'book_id' => $book->id,
            'title' => 'Review',
            'content' => str_repeat('B', 140),
        ]);

        $commenter = $this->actingMember('Ehrenmitglied');
        $this->actingAs($commenter);

        $response = $this->post(route('reviews.comments.store', $review), [
            'content' => 'Nice review',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('review_comments', [
            'review_id' => $review->id,
            'user_id' => $commenter->id,
            'content' => 'Nice review',
        ]);
        Mail::assertSent(ReviewCommentNotification::class);
    }
}
