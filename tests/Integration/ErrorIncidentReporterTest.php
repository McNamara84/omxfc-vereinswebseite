<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Jobs\SendErrorIncidentReport;
use App\Services\ErrorReporting\ErrorIncidentContextCollector;
use App\Services\ErrorReporting\ErrorIncidentReporter;
use App\Services\ErrorReporting\ErrorNotificationPolicy;
use App\Services\ErrorReporting\ErrorNotificationThrottle;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use RuntimeException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(ErrorIncidentReporterTestFixtures::class);

beforeEach(function (): void {
    Queue::fake();
    app()->instance('env', 'production');
    config(['app.env' => 'production', 'error-reporting.enabled' => true, 'error-reporting.queue' => 'default', 'error-reporting.job_delay_seconds' => 5]);
});

test('it dispatches a delayed job for the first incident', function (): void {
    app(ErrorIncidentReporter::class)->report($this->sameOriginException('Erster Fehler'));
    Queue::assertPushed(SendErrorIncidentReport::class, fn (SendErrorIncidentReport $job): bool => $job->queue === 'default' && $job->delay !== null && $job->incident->exceptionMessage === 'Erster Fehler');
});

test('it suppresses identical incidents inside the window', function (): void {
    $reporter = app(ErrorIncidentReporter::class);
    $reporter->report($this->sameOriginException('Datensatz 123 fehlt'));
    $reporter->report($this->sameOriginException('Datensatz 999 fehlt'));
    Queue::assertPushed(SendErrorIncidentReport::class, 1);
});

test('it does not dispatch when the feature is disabled', function (): void {
    config(['error-reporting.enabled' => false]);
    app(ErrorIncidentReporter::class)->report($this->sameOriginException('Fehler'));
    Queue::assertNothingPushed();
});

test('it never rethrows an internal reporting failure', function (): void {
    Log::spy();
    $policy = Mockery::mock(ErrorNotificationPolicy::class);
    $policy->shouldReceive('shouldNotify')->once()->andThrow(new RuntimeException('Reporting intern defekt'));
    $reporter = new ErrorIncidentReporter($policy, Mockery::mock(ErrorIncidentContextCollector::class), Mockery::mock(ErrorNotificationThrottle::class));
    $reporter->report(new RuntimeException('Ursprünglicher Fehler'));
    Log::shouldHaveReceived('critical')->once()->withArgs(fn (string $message, array $context): bool => str_contains($message, 'Fehlerbericht') && $context['error_reporting_internal'] === true && $context['exception_message'] === 'Reporting intern defekt');
});
