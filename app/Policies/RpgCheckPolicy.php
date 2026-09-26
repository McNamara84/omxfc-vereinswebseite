<?php

namespace App\Policies;

use App\Models\RpgCheck;
use App\Models\User;
use App\Services\RpgAccess;

class RpgCheckPolicy
{
    public function view(User $user, RpgCheck $check): bool
    {
        $access = app(RpgAccess::class);
        $team = $access->team();

        return $team && $check->batch->team_id === $team->id && $access->isMember($user->id, $team)
            && ($team->user_id === $user->id || $check->participants->contains('owner_id', $user->id));
    }

    public function manage(User $user, RpgCheck $check): bool
    {
        return $this->view($user, $check) && app(RpgAccess::class)->isLeader($user);
    }
}
