<?php

namespace App\Enums;

enum TourAssignmentSource: string
{
    case System = 'system';
    case Manual = 'manual';
    case SelfService = 'self_service';
}