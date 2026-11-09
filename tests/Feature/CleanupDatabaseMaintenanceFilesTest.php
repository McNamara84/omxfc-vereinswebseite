<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\CleanupDatabaseMaintenanceFiles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CleanupDatabaseMaintenanceFilesTestFixtures::class);

beforeEach(function (): void {
    $this->storageRoot = storage_path('framework/testing/database-maintenance-cleanup');
    File::deleteDirectory($this->storageRoot);
});

afterEach(function (): void {
    File::deleteDirectory($this->storageRoot);
});

test('cleanup rejects unsafe storage roots', function (): void {
    foreach (['', '/', base_path(), storage_path('framework/testing/../../..')] as $unsafeRoot) {
        config(['database-maintenance.storage_root' => $unsafeRoot]);
        $exitCode = Artisan::call('database-maintenance:cleanup');
        expect($exitCode)->toBe(Command::FAILURE)
            ->and(Artisan::output())->toContain('storage_root ist ungueltig');
    }
});

test('cleanup path prefix check is case sensitive on case sensitive filesystems', function (): void {
    if (PHP_OS_FAMILY === 'Windows') {
        $this->markTestSkipped('Windows path checks are intentionally case-insensitive.');
    }
    $command = new CleanupDatabaseMaintenanceFiles;
    $method = new \ReflectionMethod($command, 'isPathInside');
    expect($method->invoke($command, '/tmp/Storage/app/private', '/tmp/storage'))->toBeFalse();
});

test('cleanup rejects storage root symlink that resolves outside storage path', function (): void {
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
        expect($exitCode)->toBe(Command::FAILURE)
            ->and($oldFile)->toBeFile();
    } finally {
        if (is_link($linkRoot)) {
            @unlink($linkRoot);
        }
        File::deleteDirectory($outsideRoot);
    }
});

test('cleanup deletes old files below safe storage root', function (): void {
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
    expect($exitCode)->toBe(Command::SUCCESS);
    $this->assertFileDoesNotExist($oldFile);
    expect($freshFile)->toBeFile();
});
