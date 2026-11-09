<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\IndexRomane;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Mockery;
use ReflectionClass;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(IndexRomaneCommandTestFixtures::class);

beforeEach(function (): void {
    $this->testStoragePath = base_path('storage/testing');
    $this->app->useStoragePath($this->testStoragePath);
    File::ensureDirectoryExists($this->testStoragePath.'/app/private/romane/Z1');
    File::ensureDirectoryExists($this->testStoragePath.'/framework/views');
    config(['filesystems.disks.private.root' => $this->testStoragePath.'/app/private']);
    config(['scout.driver' => 'null']);
});

afterEach(function (): void {
    File::deleteDirectory($this->testStoragePath);
    Mockery::close();
});

test('command fails when no files found', function (): void {
    $this->artisan('romane:index')->expectsOutput('Suche nach Romanen …')->expectsOutput('Keine TXT-Dateien gefunden.')->assertExitCode(1);
});

test('meta from path extracts information', function (): void {
    $command = new IndexRomane;
    $ref = new ReflectionClass($command);
    $method = $ref->getMethod('metaFromPath');
    $result = $method->invoke($command, 'romane/Z2/123 - Titel.txt');
    $this->assertArraysAreIdentical(['Z2', '123', 'Titel'], $result);
});

test('meta from path falls back when filename has no number prefix', function (): void {
    Log::spy();
    $command = new IndexRomane;
    $ref = new ReflectionClass($command);
    $method = $ref->getMethod('metaFromPath');
    $result = $method->invoke($command, 'romane/maddrax/Glossar.txt');
    expect($result)->toBe(['maddrax', null, 'Glossar']);
    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context): bool => str_contains($message, "Dateiname 'Glossar' entspricht nicht dem erwarteten Format") && ($context['path'] ?? null) === 'romane/maddrax/Glossar.txt');
});
