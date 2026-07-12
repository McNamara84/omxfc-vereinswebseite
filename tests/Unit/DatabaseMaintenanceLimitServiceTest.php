<?php

namespace Tests\Unit;

use App\Services\DatabaseMaintenance\DatabaseMaintenanceLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DatabaseMaintenanceLimitServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $storageRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storageRoot = storage_path('framework/testing/database-maintenance-limits');
        File::deleteDirectory($this->storageRoot);
        config([
            'database-maintenance.storage_root' => $this->storageRoot,
            'database-maintenance.max_upload_mb' => 1,
            'database-maintenance.proxy_limit_mb' => 2,
            'database-maintenance.multipart_overhead_mb' => 0,
            'database-maintenance.max_uncompressed_mb' => 8,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storageRoot);

        parent::tearDown();
    }

    public function test_parse_ini_bytes_handles_php_shorthand_values(): void
    {
        $this->assertSame(512 * 1024, DatabaseMaintenanceLimitService::parseIniBytes('512K'));
        $this->assertSame(110 * 1024 * 1024, DatabaseMaintenanceLimitService::parseIniBytes('110M'));
        $this->assertSame(1024 * 1024 * 1024, DatabaseMaintenanceLimitService::parseIniBytes('1G'));
        $this->assertNull(DatabaseMaintenanceLimitService::parseIniBytes('-1'));
        $this->assertNull(DatabaseMaintenanceLimitService::parseIniBytes('0'));
        $this->assertNull(DatabaseMaintenanceLimitService::parseIniBytes('not-a-size'));
    }

    public function test_megabytes_to_bytes_accepts_numeric_configuration_values(): void
    {
        $this->assertSame(1_572_864, DatabaseMaintenanceLimitService::megabytesToBytes('1.5'));
        $this->assertNull(DatabaseMaintenanceLimitService::megabytesToBytes(null));
        $this->assertNull(DatabaseMaintenanceLimitService::megabytesToBytes(''));
        $this->assertNull(DatabaseMaintenanceLimitService::megabytesToBytes('abc'));
    }

    public function test_limits_use_lowest_known_candidate_as_effective_upload_size(): void
    {
        $limits = app(DatabaseMaintenanceLimitService::class)->limits();

        $this->assertSame(1024 * 1024, $limits['effective_upload_bytes']);
        $this->assertSame(8 * 1024 * 1024, $limits['max_uncompressed_bytes']);
        $this->assertSame($this->storageRoot, $limits['storage_root']);
        $this->assertArrayHasKey('app_max_upload', $limits['candidates']);
    }

    public function test_zero_byte_payload_candidate_can_be_effective_upload_size(): void
    {
        config([
            'database-maintenance.max_upload_mb' => 10,
            'database-maintenance.proxy_limit_mb' => 10,
            'database-maintenance.multipart_overhead_mb' => 999999,
        ]);

        $limits = app(DatabaseMaintenanceLimitService::class)->limits();

        $this->assertSame(0, $limits['php_post_payload_bytes']);
        $this->assertSame(0, $limits['candidates']['php_post_max_size']);
        $this->assertSame(0, $limits['effective_upload_bytes']);
    }

    public function test_format_bytes_returns_german_readable_values(): void
    {
        $this->assertSame('unbekannt', DatabaseMaintenanceLimitService::formatBytes(null));
        $this->assertSame('0 B', DatabaseMaintenanceLimitService::formatBytes(0));
        $this->assertSame('1,5 MB', DatabaseMaintenanceLimitService::formatBytes(1_572_864));
    }
}
