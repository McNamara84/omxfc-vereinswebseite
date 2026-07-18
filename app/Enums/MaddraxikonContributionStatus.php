<?php

namespace App\Enums;

enum MaddraxikonContributionStatus: string
{
    case Pending = 'pending';
    case Qualified = 'qualified';
    case Rejected = 'rejected';
    case Awarded = 'awarded';
}
