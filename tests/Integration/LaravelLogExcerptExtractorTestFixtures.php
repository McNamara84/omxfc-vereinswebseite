<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesErrorIncident;

trait LaravelLogExcerptExtractorTestFixtures
{
    use CreatesErrorIncident;
    use RefreshDatabase;

    /** @var array<int, string> */
    private array $temporaryFiles = [];

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
