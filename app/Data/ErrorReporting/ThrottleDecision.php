<?php

namespace App\Data\ErrorReporting;

final readonly class ThrottleDecision
{
    public function __construct(
        public bool $shouldSend,
        public int $suppressedOccurrences = 0,
    ) {}
}
