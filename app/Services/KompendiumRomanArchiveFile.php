<?php

namespace App\Services;

final readonly class KompendiumRomanArchiveFile
{
    public function __construct(
        public string $path,
        public string $downloadName,
    ) {}
}
