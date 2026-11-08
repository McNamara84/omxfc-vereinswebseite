<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\MaddraxikonCrawlException;
use App\Services\Maddraxikon\AtomicFileWriter;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use PHPUnit\Framework\TestCase;
use Tests\Support\PhpunitMetadata;

pest()->extend(TestCase::class)->use(MaddraxikonAtomicFileWriterTestFixtures::class);

covers(AtomicFileWriter::class);

beforeEach(function (): void {
    $this->files = new Filesystem;
    $this->temporaryDirectory = sys_get_temp_dir().'/maddraxikon-atomic-writer-'.bin2hex(random_bytes(8));
});

afterEach(function (): void {
    $this->files->deleteDirectory($this->temporaryDirectory);
});

test('it atomically replaces the target with a private file', function (): void {
    $target = $this->temporaryDirectory.'/Maddrax.json';
    $this->files->ensureDirectoryExists($this->temporaryDirectory);
    $this->files->put($target, 'alter Stand');
    (new AtomicFileWriter($this->files))->write($target, 'neuer Stand');
    expect($this->files->get($target))->toBe('neuer Stand')
        ->and(glob($target.'.candidate.*'))->toBe([]);
    if (PHP_OS_FAMILY !== 'Windows') {
        expect(fileperms($target) & 0777)->toBe(0600);
    }
});

PhpunitMetadata::method(test('it retries without overwriting a colliding candidate', function (): void {
    $this->files->ensureDirectoryExists($this->temporaryDirectory);
    $target = $this->temporaryDirectory.'/Maddrax.json';
    $collision = $target.'.candidate.collision';
    $available = $target.'.candidate.available';
    $this->files->put($collision, 'nicht überschreiben');
    $writer = $this->writerWithCandidates([$collision, $available]);
    $writer->write($target, 'neuer Stand');
    expect($this->files->get($collision))->toBe('nicht überschreiben')
        ->and($this->files->get($target))->toBe('neuer Stand');
    $this->assertFileDoesNotExist($available);
}), WithoutErrorHandler::class);

PhpunitMetadata::method(test('it aborts after repeated candidate name collisions', function (): void {
    $this->files->ensureDirectoryExists($this->temporaryDirectory);
    $target = $this->temporaryDirectory.'/Maddrax.json';
    $collision = $target.'.candidate.collision';
    $this->files->put($collision, 'bestehende Datei');
    $writer = $this->writerWithCandidates(array_fill(0, 10, $collision));
    expect(fn () => $writer->write($target, 'neuer Stand'))->toThrow(MaddraxikonCrawlException::class, 'nach mehreren Versuchen nicht angelegt');
}), WithoutErrorHandler::class);

PhpunitMetadata::method(test('it reports a non collision creation failure immediately', function (): void {
    $target = $this->temporaryDirectory.'/Maddrax.json';
    $writer = $this->writerWithCandidates([$this->temporaryDirectory.'/fehlendes-unterverzeichnis/candidate']);
    expect(fn () => $writer->write($target, 'neuer Stand'))->toThrow(MaddraxikonCrawlException::class, 'konnte nicht angelegt werden');
}), WithoutErrorHandler::class);
