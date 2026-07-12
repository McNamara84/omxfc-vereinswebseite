<?php

namespace Tests\Feature;

use App\Console\Commands\CleanupDatabaseMaintenanceFiles;
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
        foreach (['', '/', base_path(), storage_path('framework/testing/../../..')] as $unsafeRoot) {
            config(['database-maintenance.storage_root' => $unsafeRoot]);

            $exitCode = Artisan::call('database-maintenance:cleanup');

            $this->assertSame(Command::FAILURE, $exitCode);
            $this->assertStringContainsString('storage_root ist ungueltig', Artisan::output());
        }
    }

    public function test_cleanup_path_prefix_check_is_case_sensitive_on_case_sensitive_filesystems(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Windows path checks are intentionally case-insensitive.');
        }

        $command = new CleanupDatabaseMaintenanceFiles;
        $method = new \ReflectionMethod($command, 'isPathInside');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($command, '/tmp/Storage/app/private', '/tmp/storage'));
    }

    public function test_cleanup_rejects_storage_root_symlink_that_resolves_outside_storage_path(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Directory symlinks require elevated privileges on many Windows setups.');
        }

        $outsideRoot = base_path('database-maintenance-outside-cleanup-test');
        $linkRoot = storage_path('framework/testing/database-maintenance-cleanup-link');

        File::deleteDirectory($outsideRoot);
        if (is_link($linkRoot)) {
            @unlink($linkRoot);
        }
        File::deleteDirectory($linkRoot);
        File::ensureDirectoryExists($outsideRoot.DIRECTORY_SEPARATOR.'downloads');

        if (@symlink($outsideRoot, $linkRoot) === false) {
            File::deleteDirectory($outsideRoot);
            $this->markTestSkipped('Directory symlink could not be created in this environment.');
        }

        $oldFile = $outsideRoot.DIRECTORY_SEPARATOR.'downloads'.DIRECTORY_SEPARATOR.'old.sql.gz';
        File::put($oldFile, 'old');
        touch($oldFile, now()->subDays(2)->getTimestamp());

        try {
            config(['database-maintenance.storage_root' => $linkRoot]);

            $exitCode = Artisan::call('database-maintenance:cleanup');

            $this->assertSame(Command::FAILURE, $exitCode);
            $this->assertFileExists($oldFile);
        } finally {
            if (is_link($linkRoot)) {
                @unlink($linkRoot);
            }
            File::deleteDirectory($outsideRoot);
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
