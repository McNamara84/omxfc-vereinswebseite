<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\User;
use App\Services\DatabaseMaintenance\DatabaseDumpFile;
use App\Services\DatabaseMaintenance\DatabaseDumpService;
use App\Services\DatabaseMaintenance\DatabaseMaintenanceException;
use App\Services\DatabaseMaintenance\DatabaseRestoreService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Mockery\MockInterface;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(DatabaseRestoreServiceTestFixtures::class);

beforeEach(function (): void {
    $this->storageRoot = storage_path('framework/testing/database-maintenance-restore');
    File::deleteDirectory($this->storageRoot);
    File::ensureDirectoryExists($this->storageRoot);
    config(['database.default' => 'mysql', 'database.connections.mysql.driver' => 'mysql', 'database.connections.mysql.database' => 'omxfc_test', 'database.connections.mysql.username' => 'omxfc', 'database.connections.mysql.password' => 'secret', 'database-maintenance.storage_root' => $this->storageRoot, 'database-maintenance.max_uncompressed_mb' => 1.0E-6, 'database-maintenance.require_omxfc_dump_marker' => false]);
});

afterEach(function (): void {
    config(['database.default' => 'sqlite']);
    File::deleteDirectory($this->storageRoot);
});

test('restore aborts when plain sql exceeds uncompressed limit after pre restore dump', function (): void {
    $preRestorePath = $this->storageRoot.DIRECTORY_SEPARATOR.'pre.sql.gz';
    File::put($preRestorePath, 'backup');
    $this->mock(DatabaseDumpService::class, function (MockInterface $mock) use ($preRestorePath): void {
        $mock->shouldReceive('createPreRestoreDump')->once()->andReturn(new DatabaseDumpFile($preRestorePath, basename($preRestorePath)));
    });
    $file = UploadedFile::fake()->createWithContent('dump.sql', 'select 1;');
    try {
        app(DatabaseRestoreService::class)->restore($file, User::factory()->make(['id' => 123]));
        $this->fail('Restore should have failed because the SQL file exceeds the uncompressed limit.');
    } catch (DatabaseMaintenanceException $exception) {
        expect($exception->getMessage())->toContain('Maximalgroesse')
            ->and(File::files($this->storageRoot.DIRECTORY_SEPARATOR.'uploads'))->toHaveCount(0);
    }
});

test('prepare sql file fails closed when plain sql size cannot be read', function (): void {
    config(['database-maintenance.max_uncompressed_mb' => 10]);
    $service = app(DatabaseRestoreService::class);
    $method = new \ReflectionMethod($service, 'prepareSqlFile');
    expect(fn () => $method->invoke($service, $this->storageRoot.DIRECTORY_SEPARATOR.'missing.sql'))->toThrow(DatabaseMaintenanceException::class, 'groessenvalidiert');
});

test('restore deletes unpacked temp file when gzip exceeds uncompressed limit', function (): void {
    $this->mockPreRestoreDump();
    $file = UploadedFile::fake()->createWithContent('dump.sql.gz', gzencode('select 1;') ?: '');
    try {
        app(DatabaseRestoreService::class)->restore($file, User::factory()->make(['id' => 123]));
        $this->fail('Restore should have failed because the unpacked SQL file exceeds the uncompressed limit.');
    } catch (DatabaseMaintenanceException $exception) {
        expect($exception->getMessage())->toContain('entpackte SQL-Datei');
        $this->assertDirectoryHasNoFiles('temp');
        $this->assertDirectoryHasNoFiles('uploads');
    }
});

test('restore deletes unpacked temp file when required dump marker is missing', function (): void {
    config(['database-maintenance.max_uncompressed_mb' => 10, 'database-maintenance.require_omxfc_dump_marker' => true]);
    $this->mockPreRestoreDump();
    $file = UploadedFile::fake()->createWithContent('dump.sql.gz', gzencode('select 1;') ?: '');
    try {
        app(DatabaseRestoreService::class)->restore($file, User::factory()->make(['id' => 123]));
        $this->fail('Restore should have failed because the SQL dump marker is missing.');
    } catch (DatabaseMaintenanceException $exception) {
        expect($exception->getMessage())->toContain('Dump-Marker');
        $this->assertDirectoryHasNoFiles('temp');
        $this->assertDirectoryHasNoFiles('uploads');
    }
});
