<?php

declare(strict_types=1);

namespace Tests\Unit;

trait KompendiumRomanArchiveWriterTestFixtures
{
    /** @var list<string> */
    private array $temporaryPaths = [];

    private function temporaryFile(string $contents = ''): string
    {
        $path = tempnam(sys_get_temp_dir(), 'kompendium-test-');
        expect($path)->not->toBeFalse();
        $this->temporaryPaths[] = $path;
        if ($contents !== '') {
            expect(file_put_contents($path, $contents))->not->toBeFalse();
        }

        return $path;
    }
}
