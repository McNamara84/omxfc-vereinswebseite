<?php

namespace Tests\Feature;

use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CleanupDatabaseMaintenanceFilesTest extends TestCase
{
    use RefreshDatabase;

    private string $storageRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storageRoot = storage_path('framework/testing/database-maintenance-cleanup');
        File::deleteDirectory($this->storageRoot);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storageRoot);

        parent::tearDown();
    }

    public function test_cleanup_rejects_unsafe_storage_roots(): void
    {
        foreach (['', '/', base_path()] as $unsafeRoot) {
            config(['database-maintenance.storage_root' => $unsafeRoot]);

            $exitCode = Artisan::call('database-maintenance:cleanup');

            $this->assertSame(Command::FAILURE, $exitCode);
            $this->assertStringContainsString('storage_root ist ungueltig', Artisan::output());
        }
    }

    public function test_cleanup_deletes_old_files_below_safe_storage_root(): void
    {
        config(['database-maintenance.storage_root' => $this->storageRoot]);

        $downloadsDirectory = $this->storageRoot.DIRECTORY_SEPARATOR.'downloads';
        File::ensureDirectoryExists($downloadsDirectory);

        $oldFile = $downloadsDirectory.DIRECTORY_SEPARATOR.'old.sql.gz';
        $freshFile = $downloadsDirectory.DIRECTORY_SEPARATOR.'fresh.sql.gz';

        File::put($oldFile, 'old');
        File::put($freshFile, 'fresh');
        touch($oldFile, now()->subDays(2)->getTimestamp());
        touch($freshFile, now()->getTimestamp());

        $exitCode = Artisan::call('database-maintenance:cleanup');

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertFileDoesNotExist($oldFile);
        $this->assertFileExists($freshFile);
    }
}
