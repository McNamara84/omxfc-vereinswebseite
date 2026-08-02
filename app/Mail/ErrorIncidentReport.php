<?php

namespace App\Mail;

use App\Data\ErrorReporting\ErrorIncident;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ErrorIncidentReport extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly ErrorIncident $incident,
        private readonly string $logExcerpt,
    ) {}

    public function envelope(): Envelope
    {
        $location = $this->incident->route
            ?? $this->incident->executionName
            ?? $this->incident->executionType;

        return new Envelope(
            subject: sprintf(
                '[%s][Fehler] %s auf %s – %s',
                strtoupper($this->incident->environment),
                class_basename($this->incident->exceptionClass),
                Str::limit($location, 60, '…'),
                Str::limit($this->incident->id, 12, '…'),
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.errors.incident-report',
            with: [
                'incident' => $this->incident,
                'occurredAt' => Carbon::parse($this->incident->occurredAt)
                    ->timezone(config('app.timezone'))
                    ->format('d.m.Y H:i:s T'),
                'exceptionMessage' => Str::limit($this->incident->exceptionMessage, 2000, '…'),
                'executionLabel' => match ($this->incident->executionType) {
                    'http' => 'HTTP-Request',
                    'queue' => 'Queue-Job',
                    'scheduler' => 'Scheduler',
                    'console' => 'Konsolenbefehl',
                    default => $this->incident->executionType,
                },
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn (): string => $this->logExcerpt,
                'laravel-error-'.$this->incident->id.'.txt',
            )->withMime('text/plain'),
        ];
    }
}
