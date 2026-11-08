<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\KompendiumRomanArchiveException;
use App\Services\KompendiumRomanArchiveTemporaryFileFactory;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use PHPUnit\Framework\TestCase;
use Tests\Support\PhpunitMetadata;

pest()->extend(TestCase::class)->use(KompendiumRomanArchiveTemporaryFileFactoryTestFixtures::class);

covers(KompendiumRomanArchiveTemporaryFileFactory::class);

beforeEach(function (): void {
    $this->temporaryDirectory = sys_get_temp_dir().'/kompendium-archive-factory-'.bin2hex(random_bytes(8));
});

afterEach(function (): void {
    if (is_dir($this->temporaryDirectory)) {
        foreach (new \FilesystemIterator($this->temporaryDirectory) as $file) {
            if ($file->isFile()) {
                unlink($file->getPathname());
            }
        }
        rmdir($this->temporaryDirectory);
    }
});

test('it atomically creates a private file in the requested directory', function (): void {
    $path = (new KompendiumRomanArchiveTemporaryFileFactory)->create($this->temporaryDirectory);
    expect($path)->toBeFile()
        ->and(realpath(dirname($path)))->toBe(realpath($this->temporaryDirectory))
        ->and(str_replace('\\', '/', $path))->toMatch('/\/romane-[a-f0-9]{32}\.zip$/');
    if (PHP_OS_FAMILY !== 'Windows') {
        expect(fileperms($path) & 0777)->toBe(0600);
    }
});

PhpunitMetadata::method(test('it retries with a new name without overwriting a collision', function (): void {
    expect(mkdir($this->temporaryDirectory, 0700, true))->toBeTrue();
    $collisionPath = $this->temporaryDirectory.'/romane-collision.zip';
    $expectedPath = $this->temporaryDirectory.'/romane-available.zip';
    expect(file_put_contents($collisionPath, 'bestehende Datei'))->not->toBeFalse();
    $factory = $this->factoryWithCandidates([$collisionPath, $expectedPath]);
    expect($factory->create($this->temporaryDirectory))->toBe($expectedPath)
        ->and(file_get_contents($collisionPath))->toBe('bestehende Datei')
        ->and($expectedPath)->toBeFile();
}), WithoutErrorHandler::class);

PhpunitMetadata::method(test('it aborts after repeated name collisions', function (): void {
    expect(mkdir($this->temporaryDirectory, 0700, true))->toBeTrue();
    $collisionPath = $this->temporaryDirectory.'/romane-collision.zip';
    expect(file_put_contents($collisionPath, 'bestehende Datei'))->not->toBeFalse();
    $factory = $this->factoryWithCandidates(array_fill(0, 10, $collisionPath));
    expect(fn () => $factory->create($this->temporaryDirectory))->toThrow(KompendiumRomanArchiveException::class, 'nach mehreren Versuchen nicht angelegt');
}), WithoutErrorHandler::class);

PhpunitMetadata::method(test('it reports a non collision creation failure immediately', function (): void {
    $factory = $this->factoryWithCandidates([$this->temporaryDirectory.'/fehlendes-unterverzeichnis/romane.zip']);
    expect(fn () => $factory->create($this->temporaryDirectory))->toThrow(KompendiumRomanArchiveException::class, 'Das temporäre ZIP-Archiv konnte nicht angelegt werden.');
}), WithoutErrorHandler::class);
