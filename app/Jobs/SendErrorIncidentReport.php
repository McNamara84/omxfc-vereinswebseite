<?php

namespace App\Jobs;

use App\Data\ErrorReporting\ErrorIncident;
use App\Mail\ErrorIncidentReport;
use App\Services\ErrorReporting\ErrorReportRecipientResolver;
use App\Services\ErrorReporting\LaravelLogExcerptExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendErrorIncidentReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 60];

    public function __construct(public readonly ErrorIncident $incident) {}

    public function handle(
        ErrorReportRecipientResolver $recipients,
        LaravelLogExcerptExtractor $logExtractor,
    ): void {
        Context::add('error_notification_delivery', true);

        $recipientEmails = $recipients->resolve();

        if ($recipientEmails === []) {
            Log::critical('Fehlerbericht kann nicht versendet werden: Kein aktiver Admin-Empfänger gefunden.', [
                'error_reporting_internal' => true,
                'incident_id' => $this->incident->id,
            ]);

            return;
        }

        $logExcerpt = $logExtractor->extract($this->incident);

        foreach ($recipientEmails as $email) {
            Mail::to($email)->send(new ErrorIncidentReport($this->incident, $logExcerpt));
        }
    }

    public function failed(?Throwable $exception): void
    {
        Context::add('error_notification_delivery', true);

        try {
            Log::critical('Fehlerbericht konnte nach allen Queue-Versuchen nicht versendet werden.', [
                'error_reporting_internal' => true,
                'incident_id' => $this->incident->id,
                'exception_class' => $exception ? $exception::class : null,
                'exception_message' => $exception?->getMessage(),
            ]);
        } catch (Throwable) {
            // Keine weitere Exception aus dem Fehlerberichtssystem erzeugen.
        }
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'error-reporting',
            'incident:'.$this->incident->id,
            'fingerprint:'.$this->incident->fingerprint,
        ];
    }
}
