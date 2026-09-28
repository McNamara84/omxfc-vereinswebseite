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

    public function confirmAttendance(User $user): bool
    {
        return $user->hasAnyMitgliederTeamRole(Role::Admin, Role::Vorstand);
    }

    public function setBaxx(User $user): bool
    {
        return $user->hasAnyMitgliederTeamRole(Role::Admin, Role::Vorstand);
    }

    public function archive(User $user): bool
    {
        return $user->hasAnyMitgliederTeamRole(Role::Admin, Role::Vorstand);
    }
}
