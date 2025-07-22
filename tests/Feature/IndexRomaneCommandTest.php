<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\File;
use Mockery;
use App\Console\Commands\IndexRomane;
use ReflectionClass;

class IndexRomaneCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $testStoragePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testStoragePath = base_path('storage/testing');
        $this->app->useStoragePath($this->testStoragePath);
        File::ensureDirectoryExists($this->testStoragePath . '/app/private/romane/Z1');
        File::ensureDirectoryExists($this->testStoragePath . '/framework/views');
        config(['filesystems.disks.private.root' => $this->testStoragePath . '/app/private']);
        config(['scout.driver' => 'null']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testStoragePath);
        Mockery::close();
        parent::tearDown();
    }

    public function test_command_fails_when_no_files_found(): void
    {
        $this->artisan('romane:index')
            ->expectsOutput('Suche nach Romanen …')
            ->expectsOutput('Keine TXT-Dateien gefunden.')
            ->assertExitCode(1);
    }


    public function test_meta_from_path_extracts_information(): void
    {
        $command = new IndexRomane();
        $ref = new ReflectionClass($command);
        $method = $ref->getMethod('metaFromPath');
        $method->setAccessible(true);

        $result = $method->invoke($command, 'romane/Z2/123 - Titel.txt');

        $this->assertSame(['Z2', '123', 'Titel'], $result);
    }
}
