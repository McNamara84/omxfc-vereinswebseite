<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

class VeranstaltungPolicy
{
    public function manage(User $user): bool
    {
        return $user->canManageVeranstaltungen();
    }
}