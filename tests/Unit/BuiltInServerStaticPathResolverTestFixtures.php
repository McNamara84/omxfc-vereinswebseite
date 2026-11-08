<?php

declare(strict_types=1);

namespace Tests\Unit;

trait BuiltInServerStaticPathResolverTestFixtures
{
    private string $projectRoot;

    private function deleteDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }
        $items = scandir($path);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $itemPath = $path.DIRECTORY_SEPARATOR.$item;
            if (is_dir($itemPath)) {
                $this->deleteDirectory($itemPath);

                continue;
            }
            unlink($itemPath);
        }
        rmdir($path);
    }
}
