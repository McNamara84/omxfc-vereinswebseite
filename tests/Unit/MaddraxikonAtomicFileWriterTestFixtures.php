<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Maddraxikon\AtomicFileWriter;
use Illuminate\Filesystem\Filesystem;

trait MaddraxikonAtomicFileWriterTestFixtures
{
    private Filesystem $files;

    private string $temporaryDirectory;

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
