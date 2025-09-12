<?php

namespace Tests\Feature;

use App\Mail\ReviewCommentNotification;
use App\Models\Book;
use App\Models\Review;
use App\Models\ReviewComment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

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
        Mail::assertQueued(ReviewCommentNotification::class);
    }

    public function test_member_without_own_review_cannot_comment(): void
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $author = $this->actingMember();
        $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Foo']);
        $review = Review::create([
            'team_id' => $team->id,
            'user_id' => $author->id,
            'book_id' => $book->id,
            'title' => 'Review',
            'content' => str_repeat('B', 140),
        ]);

        $member = $this->actingMember();
        $this->actingAs($member);

        $this->post(route('reviews.comments.store', $review), [
            'content' => 'Forbidden',
        ])->assertForbidden();

        $this->assertDatabaseMissing('review_comments', [
            'review_id' => $review->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_member_without_required_role_cannot_comment(): void
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $author = $this->actingMember();
        $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Foo']);
        $review = Review::create([
            'team_id' => $team->id,
            'user_id' => $author->id,
            'book_id' => $book->id,
            'title' => 'Review',
            'content' => str_repeat('B', 140),
        ]);

        $member = $this->actingMember('Gast');
        $this->actingAs($member);

        $this->post(route('reviews.comments.store', $review), [
            'content' => 'Forbidden',
        ])->assertForbidden();

        $this->assertDatabaseMissing('review_comments', [
            'review_id' => $review->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_author_can_update_own_comment(): void
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $author = $this->actingMember();
        $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Foo']);
        $review = Review::create([
            'team_id' => $team->id,
            'user_id' => $author->id,
            'book_id' => $book->id,
            'title' => 'Review',
            'content' => str_repeat('B', 140),
        ]);
        $comment = ReviewComment::create([
            'review_id' => $review->id,
            'user_id' => $author->id,
            'content' => 'Alt',
        ]);

        $this->actingAs($author);

        $this->put(route('reviews.comments.update', $comment), [
            'content' => 'Neu',
        ])->assertRedirect();

        $this->assertDatabaseHas('review_comments', [
            'id' => $comment->id,
            'content' => 'Neu',
        ]);
    }

    public function test_other_member_cannot_update_comment(): void
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $author = $this->actingMember();
        $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Foo']);
        $review = Review::create([
            'team_id' => $team->id,
            'user_id' => $author->id,
            'book_id' => $book->id,
            'title' => 'Review',
            'content' => str_repeat('B', 140),
        ]);
        $comment = ReviewComment::create([
            'review_id' => $review->id,
            'user_id' => $author->id,
            'content' => 'Original',
        ]);

        $other = $this->actingMember();
        $this->actingAs($other);

        $this->put(route('reviews.comments.update', $comment), [
            'content' => 'Hacked',
        ])->assertForbidden();

        $this->assertDatabaseHas('review_comments', [
            'id' => $comment->id,
            'content' => 'Original',
        ]);
    }

    public function test_author_can_delete_own_comment(): void
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $author = $this->actingMember();
        $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Foo']);
        $review = Review::create([
            'team_id' => $team->id,
            'user_id' => $author->id,
            'book_id' => $book->id,
            'title' => 'Review',
            'content' => str_repeat('B', 140),
        ]);
        $comment = ReviewComment::create([
            'review_id' => $review->id,
            'user_id' => $author->id,
            'content' => 'Zu löschen',
        ]);

        $this->actingAs($author);

        $this->delete(route('reviews.comments.destroy', $comment))->assertRedirect();

        $this->assertSoftDeleted('review_comments', [
            'id' => $comment->id,
        ]);
    }

    public function test_vorstand_can_delete_any_comment(): void
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $author = $this->actingMember();
        $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Foo']);
        $review = Review::create([
            'team_id' => $team->id,
            'user_id' => $author->id,
            'book_id' => $book->id,
            'title' => 'Review',
            'content' => str_repeat('B', 140),
        ]);
        $comment = ReviewComment::create([
            'review_id' => $review->id,
            'user_id' => $author->id,
            'content' => 'Moderation',
        ]);

        $vorstand = $this->actingMember('Vorstand');
        $this->actingAs($vorstand);

        $this->delete(route('reviews.comments.destroy', $comment))->assertRedirect();

        $this->assertSoftDeleted('review_comments', [
            'id' => $comment->id,
        ]);
    }

    public function test_comment_forms_have_unique_ids(): void
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $user = $this->actingMember();
        $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Foo']);
        $review = Review::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'book_id' => $book->id,
            'title' => 'Review',
            'content' => str_repeat('B', 140),
        ]);
        $commentOne = ReviewComment::create([
            'review_id' => $review->id,
            'user_id' => $user->id,
            'content' => 'First',
        ]);
        $commentTwo = ReviewComment::create([
            'review_id' => $review->id,
            'user_id' => $user->id,
            'content' => 'Second',
        ]);

        $this->actingAs($user);
        $response = $this->get(route('reviews.show', $book));
        $html = $response->getContent();

        $this->assertSame(1, substr_count($html, 'id="content"'));
        foreach ([$commentOne, $commentTwo] as $comment) {
            $this->assertStringContainsString('id="edit-content-' . $comment->id . '"', $html);
            $this->assertStringContainsString('aria-describedby="edit-content-' . $comment->id . '-error"', $html);
            $this->assertStringContainsString('id="reply-content-' . $comment->id . '"', $html);
            $this->assertStringContainsString('aria-describedby="reply-content-' . $comment->id . '-error"', $html);
        }
    }
}
