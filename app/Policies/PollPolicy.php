<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Poll;
use App\Models\User;

class PollPolicy
{
    public function manage(User $user): bool
    {
        return $user->hasAnyRole(Role::Admin, Role::Vorstand);
    }

    public function viewResults(User $user, Poll $poll): bool
    {
        return $user->hasAnyRole(Role::Admin, Role::Vorstand);
    }
}
