<?php

namespace Tests\Unit;

use App\Services\DatabaseMaintenance\DatabaseMaintenanceAuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DatabaseMaintenanceAuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    private string $storageRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storageRoot = storage_path('framework/testing/database-maintenance-audit');
        File::deleteDirectory($this->storageRoot);
        config(['database-maintenance.storage_root' => $this->storageRoot]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storageRoot);

        parent::tearDown();
    }

    public function test_log_writes_jsonl_outside_database_and_redacts_sensitive_context(): void
    {
        $logger = app(DatabaseMaintenanceAuditLogger::class);

        $logger->log('restore_requested', [
            'filename' => 'dump.sql.gz',
            'password' => 'secret',
        ]);

        $path = $this->storageRoot.DIRECTORY_SEPARATOR.'audit.jsonl';
        $this->assertFileExists($path);

        $payload = json_decode(trim((string) file_get_contents($path)), true);

        $this->assertSame('restore_requested', $payload['event']);
        $this->assertSame('dump.sql.gz', $payload['context']['filename']);
        $this->assertSame('[redacted]', $payload['context']['password']);
    }
}
