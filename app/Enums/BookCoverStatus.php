<?php

namespace App\Enums;

enum BookCoverStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Missing = 'missing';
    case Failed = 'failed';
}
