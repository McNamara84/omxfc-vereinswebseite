<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Auktion;
use App\Models\User;

class AuktionPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isVisibleForAuthenticatedMember($user);
    }

    public function view(User $user, Auktion $auktion): bool
    {
        return $this->isVisibleForAuthenticatedMember($user);
    }

    public function manage(User $user): bool
    {
        return $user->hasAnyRole(Role::Admin, Role::Vorstand, Role::Kassenwart);
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, Auktion $auktion): bool
    {
        return $this->manage($user);
    }

    public function delete(User $user, Auktion $auktion): bool
    {
        return $this->manage($user) && ! $auktion->hasGebote();
    }

    public function bid(User $user, Auktion $auktion): bool
    {
        return $this->isVisibleForAuthenticatedMember($user)
            && $auktion->kannGeboteAnnehmen();
    }

    public function call(User $user, Auktion $auktion): bool
    {
        return $user->hasRole(Role::Vorstand) && ! $auktion->status->istAbgeschlossen();
    }

    private function isVisibleForAuthenticatedMember(User $user): bool
    {
        $role = $user->role();

        return $role instanceof Role && $role !== Role::Anwaerter;
    }
}
