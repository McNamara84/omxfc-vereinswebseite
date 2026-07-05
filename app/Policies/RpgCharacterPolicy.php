<?php

namespace App\Policies;

use App\Models\RpgCharacter;
use App\Models\User;

class RpgCharacterPolicy
{
    public function view(User $user, RpgCharacter $rpgCharacter): bool
    {
        return $rpgCharacter->user_id === $user->id;
    }

    public function delete(User $user, RpgCharacter $rpgCharacter): bool
    {
        return $rpgCharacter->user_id === $user->id;
    }
}
