<?php

namespace App\Enums;

enum TodoStatus: string
{
    case Open = 'open';
    case Assigned = 'assigned';
    case Completed = 'completed';
    case Verified = 'verified';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
