<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\KassenbuchEntry;
use App\Models\User;

class KassenbuchEntryPolicy
{
    private function role(User $user): ?Role
    {
        return $user->role();
    }

    public function viewAll(User $user): bool
    {
        $role = $this->role($user);
        return $role && in_array($role, [Role::Vorstand, Role::Admin, Role::Kassenwart], true);
    }

    public function manage(User $user): bool
    {
        $role = $this->role($user);
        return $role && in_array($role, [Role::Kassenwart, Role::Admin], true);
    }
}
