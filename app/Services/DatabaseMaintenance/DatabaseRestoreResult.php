<?php

namespace App\Services\DatabaseMaintenance;

final readonly class DatabaseRestoreResult
{
    public function __construct(
        public string $preRestoreDumpPath,
    ) {}
}
