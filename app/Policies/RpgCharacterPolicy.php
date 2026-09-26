<?php

namespace App\Policies;

use App\Models\RpgCharacter;
use App\Models\User;
use App\Services\RpgAccess;

class RpgCharacterPolicy
{
    public function improve(User $user, RpgCharacter $rpgCharacter): bool
    {
        return $this->view($user, $rpgCharacter) && app(RpgAccess::class)->isMember($user->id);
    }

    public function review(User $user, RpgCharacter $rpgCharacter): bool
    {
        return app(RpgAccess::class)->isLeader($user);
    }

    public function view(User $user, RpgCharacter $rpgCharacter): bool
    {
        return $rpgCharacter->user_id === $user->id;
    }

    public function delete(User $user, RpgCharacter $rpgCharacter): bool
    {
        return $rpgCharacter->user_id === $user->id;
    }
}
