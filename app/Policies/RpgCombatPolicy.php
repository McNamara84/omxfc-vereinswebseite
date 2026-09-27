<?php

namespace App\Policies;

use App\Models\RpgCombat;
use App\Models\User;
use App\Services\RpgAccess;

class RpgCombatPolicy
{
    public function view(User $user, RpgCombat $combat): bool
    {
        $access = app(RpgAccess::class);
        $team = $access->team();

        return $team && $combat->team_id === $team->id && $access->isMember($user->id, $team)
            && ($access->isLeader($user) || $combat->participants->contains('owner_id', $user->id));
    }

    public function rule(User $user, RpgCombat $combat): bool
    {
        return $this->view($user, $combat) && app(RpgAccess::class)->isLeader($user)
            && ! $combat->participants->contains('owner_id', $user->id);
    }
}
