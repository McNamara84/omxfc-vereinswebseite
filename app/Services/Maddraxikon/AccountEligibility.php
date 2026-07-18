<?php

namespace App\Services\Maddraxikon;

use App\Models\Team;
use App\Models\User;

final class AccountEligibility
{
    public function isEligible(User $user): bool
    {
        $membersTeam = Team::membersTeam();

        if (! $membersTeam) {
            return false;
        }

        return $membersTeam->activeUsers()
            ->whereKey($user->getKey())
            ->exists();
    }
}
