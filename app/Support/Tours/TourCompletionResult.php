<?php

namespace App\Support\Tours;

use App\Models\TourAssignment;

final readonly class TourCompletionResult
{
    /**
     * @param  array{status: 'awarded'|'already_awarded', points_awarded: int}|null  $reward
     */
    public function __construct(
        public TourAssignment $assignment,
        public ?array $reward = null,
    ) {}
}
