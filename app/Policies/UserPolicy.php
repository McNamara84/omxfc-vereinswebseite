<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

class UserPolicy
{
    private function role(User $user): ?Role
    {
        return $user->role();
    }

    public function manage(User $user): bool
    {
        $role = $this->role($user);

        return $role && in_array($role, [Role::Kassenwart, Role::Vorstand, Role::Admin], true);
    }
}
