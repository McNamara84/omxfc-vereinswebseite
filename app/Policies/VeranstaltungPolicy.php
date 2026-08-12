<?php

namespace App\Policies;

use App\Models\User;

class VeranstaltungPolicy
{
    public function manage(User $user): bool
    {
        return $user->canManageVeranstaltungen();
    }
}
