<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Mail\ReviewCommentNotification;
use App\Models\Book;
use App\Models\Review;
use App\Models\ReviewComment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CreatesUserWithRole::class, RefreshDatabase::class);

test('vorstand without own review can comment and notify author', function (): void {
    Mail::fake();
    $team = Team::membersTeam();
    $author = User::factory()->create(['current_team_id' => $team->id, 'notify_new_review' => true]);
    $team->users()->attach($author, ['role' => Role::Mitglied->value]);
    $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Foo']);
    $review = Review::create(['team_id' => $team->id, 'user_id' => $author->id, 'book_id' => $book->id, 'title' => 'Review', 'content' => str_repeat('B', 140)]);
    $commenter = $this->actingMember('Vorstand');
    $this->actingAs($commenter);
    $response = $this->post(route('reviews.comments.store', $review), ['content' => 'Nice review']);
    $response->assertRedirect();
    $this->assertDatabaseHas('review_comments', ['review_id' => $review->id, 'user_id' => $commenter->id, 'content' => 'Nice review']);
    Mail::assertQueued(ReviewCommentNotification::class);
});

test('admin without own review can comment and notify author', function (): void {
    Mail::fake();
    $team = Team::membersTeam();
    $author = User::factory()->create(['current_team_id' => $team->id, 'notify_new_review' => true]);
    $team->users()->attach($author, ['role' => Role::Mitglied->value]);
    $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Foo']);
    $review = Review::create(['team_id' => $team->id, 'user_id' => $author->id, 'book_id' => $book->id, 'title' => 'Review', 'content' => str_repeat('B', 140)]);
    $commenter = $this->actingMember('Admin');
    $this->actingAs($commenter);
    $response = $this->post(route('reviews.comments.store', $review), ['content' => 'Admin-Kommentar']);
    $response->assertRedirect();
    $this->assertDatabaseHas('review_comments', ['review_id' => $review->id, 'user_id' => $commenter->id, 'content' => 'Admin-Kommentar']);
    Mail::assertQueued(ReviewCommentNotification::class);
});

test('ehrenmitglied without own review can comment and notify author', function (): void {
    Mail::fake();
    $team = Team::membersTeam();
    $author = User::factory()->create(['current_team_id' => $team->id, 'notify_new_review' => true]);
    $team->users()->attach($author, ['role' => Role::Mitglied->value]);
    $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Foo']);
    $review = Review::create(['team_id' => $team->id, 'user_id' => $author->id, 'book_id' => $book->id, 'title' => 'Review', 'content' => str_repeat('B', 140)]);
    $commenter = $this->actingMember('Ehrenmitglied');
    $this->actingAs($commenter);
    $response = $this->post(route('reviews.comments.store', $review), ['content' => 'Ehren-Kommentar']);
    $response->assertRedirect();
    $this->assertDatabaseHas('review_comments', ['review_id' => $review->id, 'user_id' => $commenter->id, 'content' => 'Ehren-Kommentar']);
    Mail::assertQueued(ReviewCommentNotification::class);
});

test('member without own review cannot comment', function (): void {
    $team = Team::membersTeam();
    $author = $this->actingMember();
    $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Foo']);
    $review = Review::create(['team_id' => $team->id, 'user_id' => $author->id, 'book_id' => $book->id, 'title' => 'Review', 'content' => str_repeat('B', 140)]);
    $member = $this->actingMember();
    $this->actingAs($member);
    $this->post(route('reviews.comments.store', $review), ['content' => 'Forbidden'])->assertForbidden();
    $this->assertDatabaseMissing('review_comments', ['review_id' => $review->id, 'user_id' => $member->id]);
});

test('member without required role cannot comment', function (): void {
    $team = Team::membersTeam();
    $author = $this->actingMember();
    $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Foo']);
    $review = Review::create(['team_id' => $team->id, 'user_id' => $author->id, 'book_id' => $book->id, 'title' => 'Review', 'content' => str_repeat('B', 140)]);
    $member = $this->actingMember('Gast');
    $this->actingAs($member);
    $this->post(route('reviews.comments.store', $review), ['content' => 'Forbidden'])->assertForbidden();
    $this->assertDatabaseMissing('review_comments', ['review_id' => $review->id, 'user_id' => $member->id]);
});

test('author can update own comment', function (): void {
    $team = Team::membersTeam();
    $author = $this->actingMember();
    $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Foo']);
    $review = Review::create(['team_id' => $team->id, 'user_id' => $author->id, 'book_id' => $book->id, 'title' => 'Review', 'content' => str_repeat('B', 140)]);
    $comment = ReviewComment::create(['review_id' => $review->id, 'user_id' => $author->id, 'content' => 'Alt']);
    $this->actingAs($author);
    $this->put(route('reviews.comments.update', $comment), ['content' => 'Neu'])->assertRedirect();
    $this->assertDatabaseHas('review_comments', ['id' => $comment->id, 'content' => 'Neu']);
});

test('other member cannot update comment', function (): void {
    $team = Team::membersTeam();
    $author = $this->actingMember();
    $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Foo']);
    $review = Review::create(['team_id' => $team->id, 'user_id' => $author->id, 'book_id' => $book->id, 'title' => 'Review', 'content' => str_repeat('B', 140)]);
    $comment = ReviewComment::create(['review_id' => $review->id, 'user_id' => $author->id, 'content' => 'Original']);
    $other = $this->actingMember();
    $this->actingAs($other);
    $this->put(route('reviews.comments.update', $comment), ['content' => 'Hacked'])->assertForbidden();
    $this->assertDatabaseHas('review_comments', ['id' => $comment->id, 'content' => 'Original']);
});

test('author can delete own comment', function (): void {
    $team = Team::membersTeam();
    $author = $this->actingMember();
    $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Foo']);
    $review = Review::create(['team_id' => $team->id, 'user_id' => $author->id, 'book_id' => $book->id, 'title' => 'Review', 'content' => str_repeat('B', 140)]);
    $comment = ReviewComment::create(['review_id' => $review->id, 'user_id' => $author->id, 'content' => 'Zu löschen']);
    $this->actingAs($author);
    $this->delete(route('reviews.comments.destroy', $comment))->assertRedirect();
    $this->assertSoftDeleted('review_comments', ['id' => $comment->id]);
});

test('vorstand can delete any comment', function (): void {
    $team = Team::membersTeam();
    $author = $this->actingMember();
    $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Foo']);
    $review = Review::create(['team_id' => $team->id, 'user_id' => $author->id, 'book_id' => $book->id, 'title' => 'Review', 'content' => str_repeat('B', 140)]);
    $comment = ReviewComment::create(['review_id' => $review->id, 'user_id' => $author->id, 'content' => 'Moderation']);
    $vorstand = $this->actingMember('Vorstand');
    $this->actingAs($vorstand);
    $this->delete(route('reviews.comments.destroy', $comment))->assertRedirect();
    $this->assertSoftDeleted('review_comments', ['id' => $comment->id]);
});

test('comment forms have unique ids', function (): void {
    $team = Team::membersTeam();
    $user = $this->actingMember();
    $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Foo']);
    $review = Review::create(['team_id' => $team->id, 'user_id' => $user->id, 'book_id' => $book->id, 'title' => 'Review', 'content' => str_repeat('B', 140)]);
    $commentOne = ReviewComment::create(['review_id' => $review->id, 'user_id' => $user->id, 'content' => 'First']);
    $commentTwo = ReviewComment::create(['review_id' => $review->id, 'user_id' => $user->id, 'content' => 'Second']);
    $this->actingAs($user);
    $response = $this->get(route('reviews.show', $book));
    $html = $response->getContent();
    // Each comment owned by the user gets an edit form (PUT) and a reply form
    // Plus 1 main new-comment form = 5 textareas total with name="content"
    expect(substr_count($html, 'name="content"'))->toBe(5);
    // Each comment has a reply form with a hidden parent_id
    foreach ([$commentOne, $commentTwo] as $comment) {
        expect($html)->toContain('name="parent_id" value="'.$comment->id.'"');
    }
    // Ensure all textarea IDs in comment forms are unique (maryUI generates UUID-based IDs)
    preg_match_all('/<textarea[^>]+id="([^"]+)"/', $html, $matches);
    $ids = array_filter($matches[1]);
    expect($ids)->not->toBeEmpty();
    $duplicates = array_diff_assoc($ids, array_unique($ids));
    expect($duplicates)->toBeEmpty();
});
