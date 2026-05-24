<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class KassenbuchDeleteApproved extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array{id:int,beschreibung:string,buchungsdatum:string,typ:string,typ_label:string,betrag:float,betrag_formatiert:string}  $entry
     */
    public function __construct(
        public User $requester,
        public User $processor,
        public array $entry,
        public string $reasonText,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Kassenbucheintrag wurde gelöscht',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.kassenbuch.delete-approved',
            with: [
                'requester' => $this->requester,
                'processor' => $this->processor,
                'entry' => $this->entry,
                'reasonText' => $this->reasonText,
                'kassenbuchUrl' => route('kassenbuch.index'),
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