<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\MitgliedGenehmigtMail;
use App\Mail\NewReviewNotification;
use App\Mail\ReviewCommentNotification;
use App\Models\Review;
use App\Models\ReviewComment;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(MailNotificationsTestFixtures::class);

test('new review notification contains expected information', function (): void {
    $user = $this->createMember();
    $book = $this->createBook();
    $review = Review::create(['team_id' => $user->currentTeam->id, 'user_id' => $user->id, 'book_id' => $book->id, 'title' => 'Review', 'content' => str_repeat('A', 140)]);
    $mail = new NewReviewNotification($review, $user);
    expect($mail->envelope()->subject)->toBe('Neue Rezension zu deinem Roman')
        ->and($mail->envelope()->from->address)->toBe('vorstand@maddrax-fanclub.de')
        ->and($mail->content()->markdown)->toBe('emails.reviews.new-review-notification')
        ->and($mail->content()->with['review']->id)->toBe($review->id);
});

test('review comment notification contains expected information', function (): void {
    $user = $this->createMember();
    $book = $this->createBook();
    $review = Review::create(['team_id' => $user->currentTeam->id, 'user_id' => $user->id, 'book_id' => $book->id, 'title' => 'Review', 'content' => str_repeat('A', 140)]);
    $comment = ReviewComment::create(['review_id' => $review->id, 'user_id' => $user->id, 'content' => 'Nice']);
    $mail = new ReviewCommentNotification($review, $comment);
    expect($mail->envelope()->subject)->toBe('Deine Rezension wurde kommentiert')
        ->and($mail->envelope()->from->address)->toBe('vorstand@maddrax-fanclub.de')
        ->and($mail->content()->markdown)->toBe('emails.reviews.comment-notification')
        ->and($mail->content()->with['comment']->id)->toBe($comment->id);
});

test('mitglied genehmigt mail contains expected information', function (): void {
    $user = $this->createMember();
    $mail = new MitgliedGenehmigtMail($user);
    expect($mail->envelope()->subject)->toBe('Mitgliedsantrag genehmigt')
        ->and($mail->content()->markdown)->toBe('emails.mitglied.antrag-genehmigt')
        ->and($mail->content()->with['user']->id)->toBe($user->id);
});
