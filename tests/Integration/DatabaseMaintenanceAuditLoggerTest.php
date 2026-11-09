<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\DatabaseMaintenance\DatabaseMaintenanceAuditLogger;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(DatabaseMaintenanceAuditLoggerTestFixtures::class);

beforeEach(function (): void {
    $this->storageRoot = storage_path('framework/testing/database-maintenance-audit');
    File::deleteDirectory($this->storageRoot);
    config(['database-maintenance.storage_root' => $this->storageRoot]);
});

afterEach(function (): void {
    File::deleteDirectory($this->storageRoot);
});

test('log warns when jsonl file cannot be written', function (): void {
    File::ensureDirectoryExists($this->storageRoot.DIRECTORY_SEPARATOR.'audit.jsonl');
    Log::shouldReceive('warning')->once()->with('Database maintenance audit entry could not be written.', \Mockery::on(fn (array $context): bool => $context['event'] === 'restore_failed'));
    Log::shouldReceive('info')->never();
    app(DatabaseMaintenanceAuditLogger::class)->log('restore_failed');
});

test('log warns when payload cannot be json encoded', function (): void {
    Log::shouldReceive('warning')->once()->with('Database maintenance audit entry could not be encoded.', \Mockery::on(fn (array $context): bool => $context['event'] === 'restore_requested' && ($context['json_error'] ?? '') !== ''));
    Log::shouldReceive('info')->never();
    app(DatabaseMaintenanceAuditLogger::class)->log('restore_requested', ['original_name' => "\xb11"]);
    $this->assertFileDoesNotExist($this->storageRoot.DIRECTORY_SEPARATOR.'audit.jsonl');
});

test('log writes jsonl outside database and redacts sensitive context', function (): void {
    $logger = app(DatabaseMaintenanceAuditLogger::class);
    request()->server->set('REMOTE_ADDR', '203.0.113.10');
    $logger->log('restore_requested', ['filename' => 'dump.sql.gz', 'password' => 'secret']);
    $path = $this->storageRoot.DIRECTORY_SEPARATOR.'audit.jsonl';
    expect($path)->toBeFile();
    $payload = json_decode(trim((string) file_get_contents($path)), true);
    expect($payload['event'])->toBe('restore_requested')
        ->and($payload['context']['filename'])->toBe('dump.sql.gz')
        ->and($payload['context']['password'])->toBe('[redacted]')
        ->and($payload['ip'])->toBeIpAddress()
        ->toBe('203.0.113.10');
});
