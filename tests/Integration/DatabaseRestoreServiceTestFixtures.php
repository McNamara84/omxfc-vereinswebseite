<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\DatabaseMaintenance\DatabaseDumpFile;
use App\Services\DatabaseMaintenance\DatabaseDumpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Mockery\MockInterface;

trait DatabaseRestoreServiceTestFixtures
{
    use RefreshDatabase;

    private string $storageRoot;

    private function mockPreRestoreDump(): void
    {
        $preRestorePath = $this->storageRoot.DIRECTORY_SEPARATOR.'pre.sql.gz';
        File::put($preRestorePath, 'backup');
        $this->mock(DatabaseDumpService::class, function (MockInterface $mock) use ($preRestorePath): void {
            $mock->shouldReceive('createPreRestoreDump')->once()->andReturn(new DatabaseDumpFile($preRestorePath, basename($preRestorePath)));
        });
    }

    private function assertDirectoryHasNoFiles(string $section): void
    {
        $directory = $this->storageRoot.DIRECTORY_SEPARATOR.$section;
        expect(is_dir($directory) ? File::files($directory) : [])->toHaveCount(0);
    }
}
