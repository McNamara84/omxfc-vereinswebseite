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
    use \Tests\Concerns\CreatesUserWithRole;

    public function test_comment_saves_and_notifies_author(): void
    {
        Mail::fake();

        $team = Team::membersTeam();
        $author = User::factory()->create(['current_team_id' => $team->id, 'notify_new_review' => true]);
        $team->users()->attach($author, ['role' => \App\Enums\Role::Mitglied->value]);
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
        $team = Team::membersTeam();
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
        $team = Team::membersTeam();
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
        $team = Team::membersTeam();
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
        $team = Team::membersTeam();
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
        $team = Team::membersTeam();
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
        $team = Team::membersTeam();
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
        $team = Team::membersTeam();
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

        // Each comment owned by the user gets an edit form (PUT) and a reply form
        // Plus 1 main new-comment form = 5 textareas total with name="content"
        $this->assertSame(5, substr_count($html, 'name="content"'));

        // Each comment has a reply form with a hidden parent_id
        foreach ([$commentOne, $commentTwo] as $comment) {
            $this->assertStringContainsString('name="parent_id" value="'.$comment->id.'"', $html);
        }

        // Ensure all textarea IDs on the page are unique (maryUI generates UUID-based IDs)
        preg_match_all('/id="([^"]*)"/', $html, $matches);
        $ids = $matches[1];
        $duplicates = array_diff_assoc($ids, array_unique($ids));
        $this->assertEmpty($duplicates, 'Duplicate IDs found: '.implode(', ', $duplicates));
    }
}
