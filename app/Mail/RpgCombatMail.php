<?php

namespace App\Mail;

use App\Models\RpgCombatDelivery;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

class RpgCombatMail extends Mailable
{
    public function __construct(public RpgCombatDelivery $delivery) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: match ($this->delivery->kind) {
            'invitation' => 'Neue Herausforderung zum Übungskampf', 'started' => 'Dein Übungskampf beginnt', default => 'Deine Entscheidung im Übungskampf wartet',
        });
    }

    public function headers(): Headers
    {
        return new Headers(messageId: 'rpg-combat-'.$this->delivery->id.'@'.(parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.rpg-combat', with: ['combat' => $this->delivery->combat, 'decision' => $this->delivery->decision]);
    }
}
