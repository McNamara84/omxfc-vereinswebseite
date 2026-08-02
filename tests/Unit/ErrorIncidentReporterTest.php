<?php

namespace Tests\Unit;

use App\Jobs\SendErrorIncidentReport;
use App\Services\ErrorReporting\ErrorIncidentContextCollector;
use App\Services\ErrorReporting\ErrorIncidentReporter;
use App\Services\ErrorReporting\ErrorNotificationPolicy;
use App\Services\ErrorReporting\ErrorNotificationThrottle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ErrorIncidentReporterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        app()->instance('env', 'production');
        config([
            'app.env' => 'production',
            'error-reporting.enabled' => true,
            'error-reporting.queue' => 'default',
            'error-reporting.job_delay_seconds' => 5,
        ]);
    }

    public function test_it_dispatches_a_delayed_job_for_the_first_incident(): void
    {
        app(ErrorIncidentReporter::class)->report($this->sameOriginException('Erster Fehler'));

        Queue::assertPushed(SendErrorIncidentReport::class, function (SendErrorIncidentReport $job): bool {
            return $job->queue === 'default'
                && $job->delay !== null
                && $job->incident->exceptionMessage === 'Erster Fehler';
        });
    }

    public function test_it_suppresses_identical_incidents_inside_the_window(): void
    {
        $reporter = app(ErrorIncidentReporter::class);

        $reporter->report($this->sameOriginException('Datensatz 123 fehlt'));
        $reporter->report($this->sameOriginException('Datensatz 999 fehlt'));

        Queue::assertPushed(SendErrorIncidentReport::class, 1);
    }

    public function test_it_does_not_dispatch_when_the_feature_is_disabled(): void
    {
        config(['error-reporting.enabled' => false]);

        app(ErrorIncidentReporter::class)->report($this->sameOriginException('Fehler'));

        Queue::assertNothingPushed();
    }

    public function test_it_never_rethrows_an_internal_reporting_failure(): void
    {
        Log::spy();
        $policy = Mockery::mock(ErrorNotificationPolicy::class);
        $policy->shouldReceive('shouldNotify')
            ->once()
            ->andThrow(new RuntimeException('Reporting intern defekt'));

        $reporter = new ErrorIncidentReporter(
            $policy,
            Mockery::mock(ErrorIncidentContextCollector::class),
            Mockery::mock(ErrorNotificationThrottle::class),
        );

        $reporter->report(new RuntimeException('Ursprünglicher Fehler'));

        Log::shouldHaveReceived('critical')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'Fehlerbericht')
                && $context['error_reporting_internal'] === true
                && $context['exception_message'] === 'Reporting intern defekt');
    }

    private function sameOriginException(string $message): RuntimeException
    {
        return new RuntimeException($message);
    }
}
