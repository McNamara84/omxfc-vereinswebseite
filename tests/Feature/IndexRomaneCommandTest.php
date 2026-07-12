<?php

namespace Tests\Feature;

use App\Console\Commands\IndexRomane;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Mockery;
use ReflectionClass;
use Tests\TestCase;

class IndexRomaneCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $testStoragePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testStoragePath = base_path('storage/testing');
        $this->app->useStoragePath($this->testStoragePath);
        File::ensureDirectoryExists($this->testStoragePath.'/app/private/romane/Z1');
        File::ensureDirectoryExists($this->testStoragePath.'/framework/views');
        config(['filesystems.disks.private.root' => $this->testStoragePath.'/app/private']);
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
        $command = new IndexRomane;
        $ref = new ReflectionClass($command);
        $method = $ref->getMethod('metaFromPath');
        $method->setAccessible(true);

        $result = $method->invoke($command, 'romane/Z2/123 - Titel.txt');

        $this->assertArraysAreIdentical(['Z2', '123', 'Titel'], $result);
    }

    public function test_meta_from_path_falls_back_when_filename_has_no_number_prefix(): void
    {
        Log::spy();

        $command = new IndexRomane;
        $ref = new ReflectionClass($command);
        $method = $ref->getMethod('metaFromPath');
        $method->setAccessible(true);

        $result = $method->invoke($command, 'romane/maddrax/Glossar.txt');

        $this->assertSame(['maddrax', null, 'Glossar'], $result);
        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => str_contains($message, "Dateiname 'Glossar' entspricht nicht dem erwarteten Format")
                && ($context['path'] ?? null) === 'romane/maddrax/Glossar.txt'
            );
    }
}
