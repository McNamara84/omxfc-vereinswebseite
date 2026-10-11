<?php

namespace App\Policies;

use App\Models\RpgNpc;
use App\Models\User;
use App\Services\RpgAccess;

class RpgNpcPolicy
{
    public function view(User $user, RpgNpc $npc): bool
    {
        $access = app(RpgAccess::class);

        return $access->isLeader($user) && $access->team()?->id === $npc->team_id;
    }

    public function update(User $user, RpgNpc $npc): bool
    {
        return $this->view($user, $npc) && $npc->special_template_key === null;
    }

    public function delete(User $user, RpgNpc $npc): bool
    {
        return $this->view($user, $npc);
    }
}
