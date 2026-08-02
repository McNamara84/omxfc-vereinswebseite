<?php

namespace Tests\Unit;

use App\Services\ErrorReporting\LaravelLogExcerptExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesErrorIncident;
use Tests\TestCase;

class LaravelLogExcerptExtractorTest extends TestCase
{
    use CreatesErrorIncident;
    use RefreshDatabase;

    /** @var array<int, string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_it_extracts_only_records_for_the_incident_or_correlation_id(): void
    {
        $incident = $this->errorIncident();
        $path = $this->temporaryLogPath();
        file_put_contents($path, implode("\n", [
            '[2026-08-02 14:29:59] production.INFO: Vorbereitung [] {"correlation_id":"'.$incident->correlationId.'"}',
            '[2026-08-02 14:30:00] production.INFO: Fremder Request [] {"correlation_id":"33333333-3333-4333-8333-333333333333"}',
            '[2026-08-02 14:30:00] production.ERROR: Fehler mit token=secret-token {"exception":"[object] (RuntimeException: Fehler)',
            '[stacktrace]',
            '#0 /var/www/html/app/Test.php(42): test()',
            '"} {"incident_id":"'.$incident->id.'","correlation_id":"'.$incident->correlationId.'"}',
            '[2026-08-02 14:30:01] production.INFO: Fremder Abschluss [] {"correlation_id":"44444444-4444-4444-8444-444444444444"}',
        ]));
        config(['error-reporting.log_path' => $path]);

        $excerpt = app(LaravelLogExcerptExtractor::class)->extract($incident);

        $this->assertStringContainsString('Vorbereitung', $excerpt);
        $this->assertStringContainsString('production.ERROR', $excerpt);
        $this->assertStringContainsString('[stacktrace]', $excerpt);
        $this->assertStringNotContainsString('Fremder Request', $excerpt);
        $this->assertStringNotContainsString('Fremder Abschluss', $excerpt);
        $this->assertStringNotContainsString('secret-token', $excerpt);
        $this->assertStringContainsString('[REDACTED]', $excerpt);
    }

    public function test_it_resolves_a_daily_log_file_for_the_incident_date(): void
    {
        $incident = $this->errorIncident();
        $basePath = $this->temporaryLogPath('.log', create: false);
        $dailyPath = substr($basePath, 0, -4).'-2026-08-02.log';
        $this->temporaryFiles[] = $dailyPath;
        file_put_contents(
            $dailyPath,
            '[2026-08-02 14:30:00] production.ERROR: Daily [] {"incident_id":"'.$incident->id.'"}',
        );
        config(['error-reporting.log_path' => $basePath]);

        $excerpt = app(LaravelLogExcerptExtractor::class)->extract($incident);

        $this->assertStringContainsString('production.ERROR: Daily', $excerpt);
        $this->assertStringNotContainsString('Logblock war nicht verfügbar', $excerpt);
    }

    public function test_it_falls_back_to_the_snapshotted_exception_when_no_log_is_available(): void
    {
        config(['error-reporting.log_path' => '/path/does/not/exist/laravel.log']);
        $incident = $this->errorIncident([
            'exceptionMessage' => 'Fallback-Fehler',
            'exceptionTrace' => '#0 fallback trace',
        ]);

        $excerpt = app(LaravelLogExcerptExtractor::class)->extract($incident);

        $this->assertStringContainsString('Logblock war nicht verfügbar', $excerpt);
        $this->assertStringContainsString('Fallback-Fehler', $excerpt);
        $this->assertStringContainsString('#0 fallback trace', $excerpt);
    }

    public function test_it_limits_large_attachments_and_marks_the_truncation(): void
    {
        $incident = $this->errorIncident();
        $path = $this->temporaryLogPath();
        file_put_contents(
            $path,
            '[2026-08-02 14:30:00] production.ERROR: '.str_repeat('x', 3000)
            .' [] {"incident_id":"'.$incident->id.'"}',
        );
        config([
            'error-reporting.log_path' => $path,
            'error-reporting.max_attachment_kb' => 1,
        ]);

        $excerpt = app(LaravelLogExcerptExtractor::class)->extract($incident);

        $this->assertLessThanOrEqual(1024, strlen($excerpt));
        $this->assertStringContainsString('wegen des konfigurierten Größenlimits', $excerpt);
        $this->assertStringContainsString($incident->id, $excerpt);
    }

    private function temporaryLogPath(string $suffix = '.log', bool $create = true): string
    {
        $base = tempnam(storage_path('framework/testing'), 'error-report-');

        if (! is_string($base)) {
            $this->fail('Temporäre Logdatei konnte nicht erstellt werden.');
        }

        $path = $base.$suffix;
        rename($base, $path);

        if (! $create) {
            unlink($path);
        }

        $this->temporaryFiles[] = $path;

        return $path;
    }
}
