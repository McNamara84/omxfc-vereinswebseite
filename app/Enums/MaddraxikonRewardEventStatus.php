<?php

namespace App\Enums;

enum MaddraxikonRewardEventStatus: string
{
    case EvaluatedNoAward = 'evaluated_no_award';
    case Awarded = 'awarded';
    case Rejected = 'rejected';
    case Reversed = 'reversed';
}
