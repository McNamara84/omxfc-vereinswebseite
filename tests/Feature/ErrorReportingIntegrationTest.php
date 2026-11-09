<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendErrorIncidentReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

beforeEach(function (): void {
    Queue::fake();
    app()->instance('env', 'production');
    config(['app.env' => 'production', 'app.debug' => false, 'error-reporting.enabled' => true, 'error-reporting.job_delay_seconds' => 0]);
});

test('unhandled http exception dispatches report with request context', function (): void {
    $expectedUrl = rtrim((string) config('app.url'), '/').'/_test/error-reporting';
    Route::get('/_test/error-reporting', function (): never {
        throw new RuntimeException('Kontrollierter Integrationsfehler');
    })->name('test.error-reporting');
    $response = $this->withHeader('User-Agent', 'Mozilla/5.0 Chrome/126.0.6478.57 Safari/537.36')->get('/_test/error-reporting?token=must-not-be-collected');
    $response->assertStatus(500);
    Queue::assertPushed(SendErrorIncidentReport::class, fn (SendErrorIncidentReport $job): bool => $job->incident->executionType === 'http' && $job->incident->route === 'test.error-reporting' && $job->incident->url === $expectedUrl && $job->incident->method === 'GET' && $job->incident->browser === 'Google Chrome' && $job->incident->browserVersion === '126.0.6478.57' && ! str_contains($job->incident->url, 'must-not-be-collected'));
});

test('expected 404 does not dispatch a report', function (): void {
    $this->get('/_test/route-does-not-exist')->assertNotFound();
    Queue::assertNothingPushed();
});

test('correlation middleware adds a response header', function (): void {
    Route::get('/_test/correlation', fn (): string => 'ok');
    $response = $this->get('/_test/correlation');
    $response->assertOk();
    $header = $response->headers->get('X-Request-ID');
    expect($header)->toBeString()->toMatch('/^[0-9a-f-]{36}$/');
});
