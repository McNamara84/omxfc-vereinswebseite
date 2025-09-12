<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;
use App\Models\Book;
use App\Mail\NewReviewNotification;
use App\Models\Review;
use App\Mail\ReviewCommentNotification;
use App\Mail\MitgliedGenehmigtMail;
use App\Models\ReviewComment;


class MailNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function createMember(): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => \App\Enums\Role::Mitglied->value]);
        return $user;
    }

    private function createBook(): Book
    {
        return Book::create([
            'roman_number' => 1,
            'title' => 'Roman',
            'author' => 'Author',
        ]);
    }

    public function test_new_review_notification_contains_expected_information(): void
    {
        $user = $this->createMember();
        $book = $this->createBook();
        $review = Review::create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'book_id' => $book->id,
            'title' => 'Review',
            'content' => str_repeat('A', 140),
        ]);

        $mail = new NewReviewNotification($review, $user);

        $this->assertSame('Neue Rezension zu deinem Roman', $mail->envelope()->subject);
        $this->assertSame('vorstand@maddrax-fanclub.de', $mail->envelope()->from->address);
        $this->assertSame('emails.reviews.new-review-notification', $mail->content()->markdown);
        $this->assertSame($review->id, $mail->content()->with['review']->id);
    }

    public function test_review_comment_notification_contains_expected_information(): void
    {
        $user = $this->createMember();
        $book = $this->createBook();
        $review = Review::create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'book_id' => $book->id,
            'title' => 'Review',
            'content' => str_repeat('A', 140),
        ]);
        $comment = ReviewComment::create([
            'review_id' => $review->id,
            'user_id' => $user->id,
            'content' => 'Nice',
        ]);

        $mail = new ReviewCommentNotification($review, $comment);

        $this->assertSame('Deine Rezension wurde kommentiert', $mail->envelope()->subject);
        $this->assertSame('vorstand@maddrax-fanclub.de', $mail->envelope()->from->address);
        $this->assertSame('emails.reviews.comment-notification', $mail->content()->markdown);
        $this->assertSame($comment->id, $mail->content()->with['comment']->id);
    }

    public function test_mitglied_genehmigt_mail_contains_expected_information(): void
    {
        $user = $this->createMember();
        $mail = new MitgliedGenehmigtMail($user);

        $this->assertSame('Mitgliedsantrag genehmigt', $mail->envelope()->subject);
        $this->assertSame('emails.mitglied.antrag-genehmigt', $mail->content()->markdown);
        $this->assertSame($user->id, $mail->content()->with['user']->id);
    }
}
