<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ReviewComment;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(ReviewCommentModelTestFixtures::class);

test('comment can be created', function (): void {
    $reviewer = $this->createMember();
    $review = $this->createReview($reviewer);
    $commenter = $this->createMember();
    $comment = ReviewComment::create(['review_id' => $review->id, 'user_id' => $commenter->id, 'content' => 'Nice review']);
    $this->assertDatabaseHas('review_comments', ['id' => $comment->id, 'review_id' => $review->id, 'user_id' => $commenter->id, 'content' => 'Nice review']);
});

test('comment belongs to review and user', function (): void {
    $reviewer = $this->createMember();
    $review = $this->createReview($reviewer);
    $commenter = $this->createMember();
    $comment = ReviewComment::create(['review_id' => $review->id, 'user_id' => $commenter->id, 'content' => 'My opinion']);
    expect($comment->user->is($commenter))->toBeTrue()
        ->and($comment->review->is($review))->toBeTrue();
});

test('comment can have parent and children', function (): void {
    $review = $this->createReview();
    $user = $this->createMember();
    $parent = ReviewComment::create(['review_id' => $review->id, 'user_id' => $user->id, 'content' => 'Parent comment']);
    $child = ReviewComment::create(['review_id' => $review->id, 'user_id' => $user->id, 'parent_id' => $parent->id, 'content' => 'Child comment']);
    expect($child->parent->is($parent))->toBeTrue()
        ->and($parent->children->first()->is($child))->toBeTrue();
});

test('comment is soft deleted', function (): void {
    $review = $this->createReview();
    $user = $this->createMember();
    $comment = ReviewComment::create(['review_id' => $review->id, 'user_id' => $user->id, 'content' => 'To be deleted']);
    $comment->delete();
    $this->assertSoftDeleted('review_comments', ['id' => $comment->id]);
});
