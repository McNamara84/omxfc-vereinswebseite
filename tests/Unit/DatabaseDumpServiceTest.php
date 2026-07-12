<?php

namespace Tests\Unit;

use App\Services\DatabaseMaintenance\DatabaseDumpService;
use App\Services\DatabaseMaintenance\DatabaseMaintenanceException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DatabaseDumpServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $storageRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storageRoot = storage_path('framework/testing/database-maintenance-dump');
        File::deleteDirectory($this->storageRoot);
        File::ensureDirectoryExists($this->storageRoot);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storageRoot);

        parent::tearDown();
    }

    public function test_write_gzip_fails_closed_when_gzip_write_fails(): void
    {
        $path = $this->storageRoot.DIRECTORY_SEPARATOR.'read-only.sql.gz';
        File::put($path, gzencode('existing') ?: '');

        $gzip = gzopen($path, 'rb');
        $this->assertIsResource($gzip);

        $service = app(DatabaseDumpService::class);
        $method = new \ReflectionMethod($service, 'writeGzip');
        $method->setAccessible(true);

        try {
            $this->expectException(DatabaseMaintenanceException::class);
            $this->expectExceptionMessage('Dump-Datei konnte nicht geschrieben werden');

            $method->invoke($service, $gzip, 'select 1;');
        } finally {
            if (is_resource($gzip)) {
                gzclose($gzip);
            }
        }
    }
}
