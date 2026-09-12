<?php

namespace Tests\Unit;

use App\Exceptions\MaddraxikonCrawlException;
use App\Services\Maddraxikon\AtomicFileWriter;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use PHPUnit\Framework\TestCase;

#[CoversClass(AtomicFileWriter::class)]
class MaddraxikonAtomicFileWriterTest extends TestCase
{
    private Filesystem $files;

    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->temporaryDirectory = sys_get_temp_dir()
            .'/maddraxikon-atomic-writer-'.bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        $this->files->deleteDirectory($this->temporaryDirectory);

        parent::tearDown();
    }

    public function test_it_atomically_replaces_the_target_with_a_private_file(): void
    {
        $target = $this->temporaryDirectory.'/Maddrax.json';
        $this->files->ensureDirectoryExists($this->temporaryDirectory);
        $this->files->put($target, 'alter Stand');

        (new AtomicFileWriter($this->files))->write($target, 'neuer Stand');

        $this->assertSame('neuer Stand', $this->files->get($target));
        $this->assertSame([], glob($target.'.candidate.*'));

        if (PHP_OS_FAMILY !== 'Windows') {
            $this->assertSame(0600, fileperms($target) & 0777);
        }
    }

    #[WithoutErrorHandler]
    public function test_it_retries_without_overwriting_a_colliding_candidate(): void
    {
        $this->files->ensureDirectoryExists($this->temporaryDirectory);
        $target = $this->temporaryDirectory.'/Maddrax.json';
        $collision = $target.'.candidate.collision';
        $available = $target.'.candidate.available';
        $this->files->put($collision, 'nicht überschreiben');
        $writer = $this->writerWithCandidates([$collision, $available]);

        $writer->write($target, 'neuer Stand');

        $this->assertSame('nicht überschreiben', $this->files->get($collision));
        $this->assertSame('neuer Stand', $this->files->get($target));
        $this->assertFileDoesNotExist($available);
    }

    #[WithoutErrorHandler]
    public function test_it_aborts_after_repeated_candidate_name_collisions(): void
    {
        $this->files->ensureDirectoryExists($this->temporaryDirectory);
        $target = $this->temporaryDirectory.'/Maddrax.json';
        $collision = $target.'.candidate.collision';
        $this->files->put($collision, 'bestehende Datei');
        $writer = $this->writerWithCandidates(array_fill(0, 10, $collision));

        $this->expectException(MaddraxikonCrawlException::class);
        $this->expectExceptionMessage('nach mehreren Versuchen nicht angelegt');

        $writer->write($target, 'neuer Stand');
    }

    #[WithoutErrorHandler]
    public function test_it_reports_a_non_collision_creation_failure_immediately(): void
    {
        $target = $this->temporaryDirectory.'/Maddrax.json';
        $writer = $this->writerWithCandidates([
            $this->temporaryDirectory.'/fehlendes-unterverzeichnis/candidate',
        ]);

        $this->expectException(MaddraxikonCrawlException::class);
        $this->expectExceptionMessage('konnte nicht angelegt werden');

        $writer->write($target, 'neuer Stand');
    }

    /**
     * @param  list<string>  $candidates
     */
    private function writerWithCandidates(array $candidates): AtomicFileWriter
    {
        return new class($this->files, $candidates) extends AtomicFileWriter
        {
            /**
             * @param  list<string>  $candidates
             */
            public function __construct(Filesystem $files, private array $candidates)
            {
                parent::__construct($files);
            }

            protected function candidatePath(string $directory, string $targetName): string
            {
                return array_shift($this->candidates) ?? parent::candidatePath($directory, $targetName);
            }
        };
    }
}
