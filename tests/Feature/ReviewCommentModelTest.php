<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use App\Models\ReviewComment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewCommentModelTest extends TestCase
{
    use RefreshDatabase;

    private function createMember(string $role = 'Mitglied'): User
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role]);

        return $user;
    }

    private function createReview(?User $author = null): Review
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $author = $author ?: $this->createMember();
        $book = Book::create([
            'roman_number' => 1,
            'title' => 'Roman1',
            'author' => 'Foo',
        ]);

        return Review::create([
            'team_id' => $team->id,
            'user_id' => $author->id,
            'book_id' => $book->id,
            'title' => 'Review',
            'content' => str_repeat('B', 140),
        ]);
    }

    public function test_comment_can_be_created(): void
    {
        $reviewer = $this->createMember();
        $review = $this->createReview($reviewer);
        $commenter = $this->createMember();

        $comment = ReviewComment::create([
            'review_id' => $review->id,
            'user_id' => $commenter->id,
            'content' => 'Nice review',
        ]);

        $this->assertDatabaseHas('review_comments', [
            'id' => $comment->id,
            'review_id' => $review->id,
            'user_id' => $commenter->id,
            'content' => 'Nice review',
        ]);
    }

    public function test_comment_belongs_to_review_and_user(): void
    {
        $reviewer = $this->createMember();
        $review = $this->createReview($reviewer);
        $commenter = $this->createMember();

        $comment = ReviewComment::create([
            'review_id' => $review->id,
            'user_id' => $commenter->id,
            'content' => 'My opinion',
        ]);

        $this->assertTrue($comment->user->is($commenter));
        $this->assertTrue($comment->review->is($review));
    }

    public function test_comment_can_have_parent_and_children(): void
    {
        $review = $this->createReview();
        $user = $this->createMember();

        $parent = ReviewComment::create([
            'review_id' => $review->id,
            'user_id' => $user->id,
            'content' => 'Parent comment',
        ]);

        $child = ReviewComment::create([
            'review_id' => $review->id,
            'user_id' => $user->id,
            'parent_id' => $parent->id,
            'content' => 'Child comment',
        ]);

        $this->assertTrue($child->parent->is($parent));
        $this->assertTrue($parent->children->first()->is($child));
    }

    public function test_comment_is_soft_deleted(): void
    {
        $review = $this->createReview();
        $user = $this->createMember();

        $comment = ReviewComment::create([
            'review_id' => $review->id,
            'user_id' => $user->id,
            'content' => 'To be deleted',
        ]);

        $comment->delete();

        $this->assertSoftDeleted('review_comments', [
            'id' => $comment->id,
        ]);
    }
}
