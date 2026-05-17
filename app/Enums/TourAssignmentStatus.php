<?php

namespace App\Enums;

enum TourAssignmentStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function isOpen(): bool
    {
        return $this !== self::Completed;
    }
}