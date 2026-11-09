<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\DatabaseMaintenance\DatabaseMaintenanceLimitService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(DatabaseMaintenanceLimitServiceTestFixtures::class);

beforeEach(function (): void {
    $this->storageRoot = storage_path('framework/testing/database-maintenance-limits');
    File::deleteDirectory($this->storageRoot);
    config(['database-maintenance.storage_root' => $this->storageRoot, 'database-maintenance.max_upload_mb' => 1, 'database-maintenance.proxy_limit_mb' => 2, 'database-maintenance.multipart_overhead_mb' => 0, 'database-maintenance.max_uncompressed_mb' => 8]);
});

afterEach(function (): void {
    File::deleteDirectory($this->storageRoot);
});

test('parse ini bytes handles php shorthand values', function (): void {
    expect(DatabaseMaintenanceLimitService::parseIniBytes('512K'))->toBe(512 * 1024)
        ->and(DatabaseMaintenanceLimitService::parseIniBytes('110M'))->toBe(110 * 1024 * 1024)
        ->and(DatabaseMaintenanceLimitService::parseIniBytes('1G'))->toBe(1024 * 1024 * 1024)
        ->and(DatabaseMaintenanceLimitService::parseIniBytes('-1'))->toBeNull()
        ->and(DatabaseMaintenanceLimitService::parseIniBytes('0'))->toBeNull()
        ->and(DatabaseMaintenanceLimitService::parseIniBytes('not-a-size'))->toBeNull();
});

test('megabytes to bytes accepts numeric configuration values', function (): void {
    expect(DatabaseMaintenanceLimitService::megabytesToBytes('1.5'))->toBe(1572864)
        ->and(DatabaseMaintenanceLimitService::megabytesToBytes(null))->toBeNull()
        ->and(DatabaseMaintenanceLimitService::megabytesToBytes(''))->toBeNull()
        ->and(DatabaseMaintenanceLimitService::megabytesToBytes('abc'))->toBeNull();
});

test('limits use lowest known candidate as effective upload size', function (): void {
    $limits = app(DatabaseMaintenanceLimitService::class)->limits();
    expect($limits['effective_upload_bytes'])->toBe(1024 * 1024)
        ->and($limits['max_uncompressed_bytes'])->toBe(8 * 1024 * 1024)
        ->and($limits['storage_root'])->toBe($this->storageRoot)
        ->and($limits['candidates'])->toHaveKey('app_max_upload');
});

test('zero byte payload candidate can be effective upload size', function (): void {
    config(['database-maintenance.max_upload_mb' => 10, 'database-maintenance.proxy_limit_mb' => 10, 'database-maintenance.multipart_overhead_mb' => 999999]);
    $limits = app(DatabaseMaintenanceLimitService::class)->limits();
    expect($limits['php_post_payload_bytes'])->toBe(0)
        ->and($limits['candidates']['php_post_max_size'])->toBe(0)
        ->and($limits['effective_upload_bytes'])->toBe(0);
});

test('format bytes returns german readable values', function (): void {
    expect(DatabaseMaintenanceLimitService::formatBytes(null))->toBe('unbekannt')
        ->and(DatabaseMaintenanceLimitService::formatBytes(0))->toBe('0 B')
        ->and(DatabaseMaintenanceLimitService::formatBytes(1572864))->toBe('1,5 MB');
});
