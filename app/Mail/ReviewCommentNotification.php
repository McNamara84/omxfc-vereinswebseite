<?php

namespace App\Mail;

use App\Models\Review;
use App\Models\ReviewComment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReviewCommentNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Review $review,
        public ReviewComment $comment
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('vorstand@maddrax-fanclub.de'),
            subject: 'Deine Rezension wurde kommentiert'
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.reviews.comment-notification',
            with: [
                'review' => $this->review,
                'comment' => $this->comment,
                'reviewUrl' => route('reviews.show', $this->review->book),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
