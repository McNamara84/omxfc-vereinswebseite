<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\DatabaseMaintenance\DatabaseDumpService;
use App\Services\DatabaseMaintenance\DatabaseMaintenanceException;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(DatabaseDumpServiceTestFixtures::class);

beforeEach(function (): void {
    $this->storageRoot = storage_path('framework/testing/database-maintenance-dump');
    File::deleteDirectory($this->storageRoot);
    File::ensureDirectoryExists($this->storageRoot);
});

afterEach(function (): void {
    File::deleteDirectory($this->storageRoot);
});

test('write gzip fails closed when gzip write fails', function (): void {
    $path = $this->storageRoot.DIRECTORY_SEPARATOR.'read-only.sql.gz';
    File::put($path, gzencode('existing') ?: '');
    $gzip = gzopen($path, 'rb');
    expect($gzip)->toBeResource();
    $service = app(DatabaseDumpService::class);
    $method = new \ReflectionMethod($service, 'writeGzip');
    try {
        expect(fn () => $method->invoke($service, $gzip, 'select 1;'))->toThrow(DatabaseMaintenanceException::class, 'Dump-Datei konnte nicht geschrieben werden');
    } finally {
        if (is_resource($gzip)) {
            gzclose($gzip);
        }
    }
});
