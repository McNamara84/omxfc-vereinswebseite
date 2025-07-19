<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Address;
use App\Models\Review;
use App\Models\User;

class NewReviewNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Review $review,
        public User $recipient
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('vorstand@maddrax-fanclub.de'),
            subject: 'Neue Rezension zu deinem Roman'
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.reviews.new-review-notification',
            with: [
                'review' => $this->review,
                'user' => $this->recipient,
                'reviewUrl' => route('reviews.show', $this->review->book),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
