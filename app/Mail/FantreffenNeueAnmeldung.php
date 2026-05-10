<?php

namespace App\Mail;

use App\Models\FantreffenAnmeldung;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FantreffenNeueAnmeldung extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public FantreffenAnmeldung $anmeldung;

    /**
     * Create a new message instance.
     */
    public function __construct(FantreffenAnmeldung $anmeldung)
    {
        $this->anmeldung = $anmeldung;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $titel = $this->anmeldung->veranstaltung?->titel ?? 'einer Veranstaltung';

        return new Envelope(
            subject: 'Neue Anmeldung zu '.$titel,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.fantreffen.neue-anmeldung',
            with: [
                'anmeldung' => $this->anmeldung,
                'veranstaltung' => $this->anmeldung->veranstaltung,
                'fullName' => $this->anmeldung->full_name,
                'email' => $this->anmeldung->registrant_email,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
