<?php

namespace App\Mail;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class ProfileContactUpdated extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Carbon $contactChangedAt;

    /**
     * @param  array<int, string>  $changedContactLabels
     */
    public function __construct(
        public User $user,
        public array $changedContactLabels,
        CarbonInterface|string|null $contactChangedAt = null,
    ) {
        $this->contactChangedAt = Carbon::parse($contactChangedAt ?? $user->contact_released_at ?? now());
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Kontaktdaten im Profil aktualisiert',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.profile.contact-updated',
            with: [
                'user' => $this->user,
                'changedContactLabels' => $this->changedContactLabels,
                'contactChangedAt' => $this->contactChangedAt,
                'profileUrl' => route('profile.view', $this->user),
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
