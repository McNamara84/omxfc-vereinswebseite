<?php

namespace App\Mail;

use App\Models\FantreffenAnmeldung;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FantreffenAnmeldungBestaetigung extends Mailable implements ShouldQueue
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
        return new Envelope(
            subject: 'Deine Anmeldung zum Maddrax-Fantreffen 2026',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.fantreffen.anmeldung-bestaetigung',
            with: [
                'anmeldung' => $this->anmeldung,
                'fullName' => $this->anmeldung->full_name,
                'paymentRequired' => $this->anmeldung->requiresPayment(),
                'paymentAmount' => $this->anmeldung->payment_amount,
                'zahlungsUrl' => route('fantreffen.2026.bestaetigung', ['id' => $this->anmeldung->id]),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
