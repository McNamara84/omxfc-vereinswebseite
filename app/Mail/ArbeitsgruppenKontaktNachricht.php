<?php

namespace App\Mail;

use App\Models\Team;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ArbeitsgruppenKontaktNachricht extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Team $team,
        public string $absenderName,
        public string $absenderEmail,
        public string $nachricht,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Kontaktanfrage an '.$this->team->name,
            replyTo: [new Address($this->absenderEmail, $this->absenderName)],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.arbeitsgruppen.kontakt',
            with: [
                'team' => $this->team,
                'absenderName' => $this->absenderName,
                'absenderEmail' => $this->absenderEmail,
                'nachricht' => $this->nachricht,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}