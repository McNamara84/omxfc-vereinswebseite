<?php

namespace App\Policies;

use App\Models\RpgCharacter;
use App\Models\User;
use App\Services\RpgAccess;
use Illuminate\Support\Facades\Gate;

class RpgCharacterPolicy
{
    public function rename(User $user, RpgCharacter $rpgCharacter): bool
    {
        return $this->view($user, $rpgCharacter) && Gate::forUser($user)->allows('access-rpg-char-editor');
    }

    public function viewInventory(User $user, RpgCharacter $rpgCharacter): bool
    {
        return $this->view($user, $rpgCharacter) || $this->manageInventory($user, $rpgCharacter);
    }

    public function manageInventory(User $user, RpgCharacter $rpgCharacter): bool
    {
        $access = app(RpgAccess::class);

        return $access->isLeader($user) && $access->isMember($rpgCharacter->user_id);
    }

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
