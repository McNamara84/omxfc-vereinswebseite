<?php

namespace App\Mail;

use App\Data\WebsiteFeedback\WebsiteFeedbackData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Str;

class WebsiteFeedbackReceived extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly WebsiteFeedbackData $feedback) {}

    public function envelope(): Envelope
    {
        $replyTo = [];

        if (! $this->feedback->isAnonymous() && $this->feedback->reporterEmail !== null) {
            $replyTo[] = new Address(
                $this->feedback->reporterEmail,
                $this->feedback->reporterName ?? $this->feedback->reporterEmail,
            );
        }

        return new Envelope(
            subject: sprintf(
                '[Website-Feedback] %s – %s',
                $this->feedback->category->label(),
                Str::limit($this->feedback->pageTitle, 70, '…'),
            ),
            replyTo: $replyTo,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.feedback.website-feedback',
            with: [
                'feedback' => $this->feedback,
                'submittedAt' => $this->feedback->submittedAt
                    ->timezone(config('app.timezone'))
                    ->format('d.m.Y H:i:s T'),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
