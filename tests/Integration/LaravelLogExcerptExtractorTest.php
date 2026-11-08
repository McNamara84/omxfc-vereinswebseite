<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\ErrorReporting\LaravelLogExcerptExtractor;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(LaravelLogExcerptExtractorTestFixtures::class);

afterEach(function (): void {
    foreach ($this->temporaryFiles as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
});

test('it extracts only records for the incident or correlation id', function (): void {
    $incident = $this->errorIncident();
    $path = $this->temporaryLogPath();
    file_put_contents($path, implode("\n", ['[2026-08-02 14:29:59] production.INFO: Vorbereitung [] {"correlation_id":"'.$incident->correlationId.'"}', '[2026-08-02 14:30:00] production.INFO: Fremder Request [] {"correlation_id":"33333333-3333-4333-8333-333333333333"}', '[2026-08-02 14:30:00] production.ERROR: Fehler mit token=secret-token {"exception":"[object] (RuntimeException: Fehler)', '[stacktrace]', '#0 /var/www/html/app/Test.php(42): test()', '"} {"incident_id":"'.$incident->id.'","correlation_id":"'.$incident->correlationId.'"}', '[2026-08-02 14:30:01] production.INFO: Fremder Abschluss [] {"correlation_id":"44444444-4444-4444-8444-444444444444"}']));
    config(['error-reporting.log_path' => $path]);
    $excerpt = app(LaravelLogExcerptExtractor::class)->extract($incident);
    expect($excerpt)->toContain('Vorbereitung')
        ->toContain('production.ERROR')
        ->toContain('[stacktrace]')->not->toContain('Fremder Request')->not->toContain('Fremder Abschluss')->not->toContain('secret-token')
        ->toContain('[REDACTED]');
});

test('it resolves a daily log file for the incident date', function (): void {
    $incident = $this->errorIncident();
    $basePath = $this->temporaryLogPath('.log', create: false);
    $dailyPath = substr($basePath, 0, -4).'-2026-08-02.log';
    $this->temporaryFiles[] = $dailyPath;
    file_put_contents($dailyPath, '[2026-08-02 14:30:00] production.ERROR: Daily [] {"incident_id":"'.$incident->id.'"}');
    config(['error-reporting.log_path' => $basePath]);
    $excerpt = app(LaravelLogExcerptExtractor::class)->extract($incident);
    expect($excerpt)->toContain('production.ERROR: Daily')->not->toContain('Logblock war nicht verfügbar');
});

test('it falls back to the snapshotted exception when no log is available', function (): void {
    config(['error-reporting.log_path' => '/path/does/not/exist/laravel.log']);
    $incident = $this->errorIncident(['exceptionMessage' => 'Fallback-Fehler', 'exceptionTrace' => '#0 fallback trace']);
    $excerpt = app(LaravelLogExcerptExtractor::class)->extract($incident);
    expect($excerpt)->toContain('Logblock war nicht verfügbar')
        ->toContain('Fallback-Fehler')
        ->toContain('#0 fallback trace');
});

test('it limits large attachments and marks the truncation', function (): void {
    $incident = $this->errorIncident();
    $path = $this->temporaryLogPath();
    file_put_contents($path, '[2026-08-02 14:30:00] production.ERROR: '.str_repeat('x', 3000).' [] {"incident_id":"'.$incident->id.'"}');
    config(['error-reporting.log_path' => $path, 'error-reporting.max_attachment_kb' => 1]);
    $excerpt = app(LaravelLogExcerptExtractor::class)->extract($incident);
    expect(strlen($excerpt))->toBeLessThanOrEqual(1024)
        ->and($excerpt)->toContain('wegen des konfigurierten Größenlimits')
        ->toContain($incident->id);
});
