<?php

namespace App\Policies;

use App\Models\RpgItemType;
use App\Models\User;
use App\Services\RpgAccess;

class RpgItemPolicy
{
    public function update(User $user, RpgItemType $itemType): bool
    {
        $access = app(RpgAccess::class);

        return $access->isLeader($user) && $access->team()?->id === $itemType->team_id;
    }
}
