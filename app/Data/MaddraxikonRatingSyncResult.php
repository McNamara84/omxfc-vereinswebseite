<?php

namespace App\Data;

final readonly class MaddraxikonRatingSyncResult
{
    public function __construct(
        public int $candidates = 0,
        public int $updated = 0,
        public int $removed = 0,
        public int $skipped = 0,
        public bool $disabled = false,
        public bool $dryRun = false,
    ) {}
}
