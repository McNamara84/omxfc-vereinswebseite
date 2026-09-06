<?php

namespace Tests\Unit;

use App\Services\KompendiumRomanArchiveException;
use App\Services\KompendiumRomanArchiveTemporaryFileFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use PHPUnit\Framework\TestCase;

#[CoversClass(KompendiumRomanArchiveTemporaryFileFactory::class)]
class KompendiumRomanArchiveTemporaryFileFactoryTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = sys_get_temp_dir()
            .'/kompendium-archive-factory-'.bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->temporaryDirectory)) {
            foreach (new \FilesystemIterator($this->temporaryDirectory) as $file) {
                if ($file->isFile()) {
                    unlink($file->getPathname());
                }
            }

            rmdir($this->temporaryDirectory);
        }

        parent::tearDown();
    }

    public function test_it_atomically_creates_a_private_file_in_the_requested_directory(): void
    {
        $path = (new KompendiumRomanArchiveTemporaryFileFactory)->create($this->temporaryDirectory);

        $this->assertFileExists($path);
        $this->assertSame(realpath($this->temporaryDirectory), realpath(dirname($path)));
        $this->assertMatchesRegularExpression(
            '/\/romane-[a-f0-9]{32}\.zip$/',
            str_replace('\\', '/', $path),
        );
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->assertSame(0600, fileperms($path) & 0777);
        }
    }

    #[WithoutErrorHandler]
    public function test_it_retries_with_a_new_name_without_overwriting_a_collision(): void
    {
        $this->assertTrue(mkdir($this->temporaryDirectory, 0700, true));
        $collisionPath = $this->temporaryDirectory.'/romane-collision.zip';
        $expectedPath = $this->temporaryDirectory.'/romane-available.zip';
        $this->assertNotFalse(file_put_contents($collisionPath, 'bestehende Datei'));

        $factory = $this->factoryWithCandidates([$collisionPath, $expectedPath]);

        $this->assertSame($expectedPath, $factory->create($this->temporaryDirectory));
        $this->assertSame('bestehende Datei', file_get_contents($collisionPath));
        $this->assertFileExists($expectedPath);
    }

    #[WithoutErrorHandler]
    public function test_it_aborts_after_repeated_name_collisions(): void
    {
        $this->assertTrue(mkdir($this->temporaryDirectory, 0700, true));
        $collisionPath = $this->temporaryDirectory.'/romane-collision.zip';
        $this->assertNotFalse(file_put_contents($collisionPath, 'bestehende Datei'));

        $factory = $this->factoryWithCandidates(array_fill(0, 10, $collisionPath));

        $this->expectException(KompendiumRomanArchiveException::class);
        $this->expectExceptionMessage('nach mehreren Versuchen nicht angelegt');

        $factory->create($this->temporaryDirectory);
    }

    #[WithoutErrorHandler]
    public function test_it_reports_a_non_collision_creation_failure_immediately(): void
    {
        $factory = $this->factoryWithCandidates([
            $this->temporaryDirectory.'/fehlendes-unterverzeichnis/romane.zip',
        ]);

        $this->expectException(KompendiumRomanArchiveException::class);
        $this->expectExceptionMessage('Das temporäre ZIP-Archiv konnte nicht angelegt werden.');

        $factory->create($this->temporaryDirectory);
    }

    /**
     * @param  list<string>  $candidates
     */
    private function factoryWithCandidates(array $candidates): KompendiumRomanArchiveTemporaryFileFactory
    {
        return new class($candidates) extends KompendiumRomanArchiveTemporaryFileFactory
        {
            /**
             * @param  list<string>  $candidates
             */
            public function __construct(private array $candidates) {}

            protected function candidatePath(string $directory): string
            {
                return array_shift($this->candidates) ?? parent::candidatePath($directory);
            }
        };
    }
}
