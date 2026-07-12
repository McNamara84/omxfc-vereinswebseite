<?php

namespace App\Services\DatabaseMaintenance;

final readonly class DatabaseDumpFile
{
    public function __construct(
        public string $path,
        public string $downloadName,
    ) {}
}
