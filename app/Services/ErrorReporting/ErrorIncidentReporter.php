<?php

namespace App\Services\ErrorReporting;

use App\Jobs\SendErrorIncidentReport;
use Illuminate\Support\Facades\Log;
use Throwable;

class ErrorIncidentReporter
{
    public function __construct(
        private readonly ErrorNotificationPolicy $policy,
        private readonly ErrorIncidentContextCollector $collector,
        private readonly ErrorNotificationThrottle $throttle,
    ) {}

    public function report(Throwable $exception): void
    {
        try {
            if (! $this->policy->shouldNotify($exception)) {
                return;
            }

            $incident = $this->collector->collect($exception);
            $decision = $this->throttle->decide($incident->fingerprint);

            if (! $decision->shouldSend) {
                Log::notice('Identischer Fehlerbericht wurde innerhalb des Drosselungsfensters unterdrückt.', [
                    'error_reporting_internal' => true,
                    'incident_id' => $incident->id,
                    'fingerprint' => $incident->fingerprint,
                ]);

                return;
            }

            $job = new SendErrorIncidentReport(
                $incident->withSuppressedOccurrences($decision->suppressedOccurrences),
            );

            $queue = config('error-reporting.queue', 'default');

            if (is_string($queue) && $queue !== '') {
                $job->onQueue($queue);
            }

            $delaySeconds = max(0, (int) config('error-reporting.job_delay_seconds', 5));

            if ($delaySeconds > 0) {
                $job->delay(now()->addSeconds($delaySeconds));
            }

            dispatch($job);
        } catch (Throwable $reportingException) {
            $this->logReporterFailure($reportingException);
        }
    }

    private function logReporterFailure(Throwable $exception): void
    {
        try {
            Log::critical('Automatischer Fehlerbericht konnte nicht vorbereitet werden.', [
                'error_reporting_internal' => true,
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);
        } catch (Throwable) {
            // Das Fehlerreporting darf die ursprüngliche Exception niemals überdecken.
        }
    }
}
