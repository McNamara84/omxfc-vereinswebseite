<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\DatabaseMaintenance\DatabaseDumpFile;
use App\Services\DatabaseMaintenance\DatabaseDumpService;
use App\Services\DatabaseMaintenance\DatabaseMaintenanceException;
use App\Services\DatabaseMaintenance\DatabaseRestoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Mockery\MockInterface;
use Tests\TestCase;

class DatabaseRestoreServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $storageRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storageRoot = storage_path('framework/testing/database-maintenance-restore');
        File::deleteDirectory($this->storageRoot);
        File::ensureDirectoryExists($this->storageRoot);
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.driver' => 'mysql',
            'database.connections.mysql.database' => 'omxfc_test',
            'database.connections.mysql.username' => 'omxfc',
            'database.connections.mysql.password' => 'secret',
            'database-maintenance.storage_root' => $this->storageRoot,
            'database-maintenance.max_uncompressed_mb' => 0.000001,
            'database-maintenance.require_omxfc_dump_marker' => false,
        ]);
    }

    protected function tearDown(): void
    {
        config(['database.default' => 'sqlite']);
        File::deleteDirectory($this->storageRoot);

        parent::tearDown();
    }

    public function test_restore_aborts_when_plain_sql_exceeds_uncompressed_limit_after_pre_restore_dump(): void
    {
        $preRestorePath = $this->storageRoot.DIRECTORY_SEPARATOR.'pre.sql.gz';
        File::put($preRestorePath, 'backup');

        $this->mock(DatabaseDumpService::class, function (MockInterface $mock) use ($preRestorePath): void {
            $mock->shouldReceive('createPreRestoreDump')
                ->once()
                ->andReturn(new DatabaseDumpFile($preRestorePath, basename($preRestorePath)));
        });

        $file = UploadedFile::fake()->createWithContent('dump.sql', 'select 1;');

        try {
            app(DatabaseRestoreService::class)->restore($file, User::factory()->make(['id' => 123]));
            $this->fail('Restore should have failed because the SQL file exceeds the uncompressed limit.');
        } catch (DatabaseMaintenanceException $exception) {
            $this->assertStringContainsString('Maximalgroesse', $exception->getMessage());
            $this->assertCount(0, File::files($this->storageRoot.DIRECTORY_SEPARATOR.'uploads'));
        }
    }

    public function test_restore_deletes_unpacked_temp_file_when_gzip_exceeds_uncompressed_limit(): void
    {
        $this->mockPreRestoreDump();

        $file = UploadedFile::fake()->createWithContent('dump.sql.gz', gzencode('select 1;') ?: '');

        try {
            app(DatabaseRestoreService::class)->restore($file, User::factory()->make(['id' => 123]));
            $this->fail('Restore should have failed because the unpacked SQL file exceeds the uncompressed limit.');
        } catch (DatabaseMaintenanceException $exception) {
            $this->assertStringContainsString('entpackte SQL-Datei', $exception->getMessage());
            $this->assertDirectoryHasNoFiles('temp');
            $this->assertDirectoryHasNoFiles('uploads');
        }
    }

    public function test_restore_deletes_unpacked_temp_file_when_required_dump_marker_is_missing(): void
    {
        config([
            'database-maintenance.max_uncompressed_mb' => 10,
            'database-maintenance.require_omxfc_dump_marker' => true,
        ]);
        $this->mockPreRestoreDump();

        $file = UploadedFile::fake()->createWithContent('dump.sql.gz', gzencode('select 1;') ?: '');

        try {
            app(DatabaseRestoreService::class)->restore($file, User::factory()->make(['id' => 123]));
            $this->fail('Restore should have failed because the SQL dump marker is missing.');
        } catch (DatabaseMaintenanceException $exception) {
            $this->assertStringContainsString('Dump-Marker', $exception->getMessage());
            $this->assertDirectoryHasNoFiles('temp');
            $this->assertDirectoryHasNoFiles('uploads');
        }
    }

    private function mockPreRestoreDump(): void
    {
        $preRestorePath = $this->storageRoot.DIRECTORY_SEPARATOR.'pre.sql.gz';
        File::put($preRestorePath, 'backup');

        $this->mock(DatabaseDumpService::class, function (MockInterface $mock) use ($preRestorePath): void {
            $mock->shouldReceive('createPreRestoreDump')
                ->once()
                ->andReturn(new DatabaseDumpFile($preRestorePath, basename($preRestorePath)));
        });
    }

    private function assertDirectoryHasNoFiles(string $section): void
    {
        $directory = $this->storageRoot.DIRECTORY_SEPARATOR.$section;

        $this->assertCount(0, is_dir($directory) ? File::files($directory) : []);
    }
}
