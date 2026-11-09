<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\KompendiumRomanArchiveTemporaryFileFactory;

trait KompendiumRomanArchiveTemporaryFileFactoryTestFixtures
{
    private string $temporaryDirectory;

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
