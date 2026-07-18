<?php

namespace App\Mail;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class MaddraxikonAccountLinked extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $wikiUsername,
        public readonly CarbonImmutable $verifiedAt,
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Dein Maddraxikon-Konto wurde verknüpft',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.maddraxikon.account-linked',
            with: [
                'wikiUsername' => $this->wikiUsername,
                'verifiedAt' => $this->verifiedAt,
                'profileUrl' => route('profile.show'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
