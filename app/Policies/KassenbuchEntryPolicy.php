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

    /**
     * Determine if the user can request to edit the entry.
     */
    public function requestEdit(User $user, KassenbuchEntry $entry): bool
    {
        $role = $this->role($user);

        // Only Kassenwart/Admin can request edits
        if (! $role || ! in_array($role, [Role::Kassenwart, Role::Admin], true)) {
            return false;
        }

        // Cannot request if a pending request already exists
        return ! $entry->hasPendingEditRequest();
    }

    /**
     * Determine if the user can edit the entry.
     */
    public function edit(User $user, KassenbuchEntry $entry): bool
    {
        $role = $this->role($user);

        // Only Kassenwart/Admin can edit
        if (! $role || ! in_array($role, [Role::Kassenwart, Role::Admin], true)) {
            return false;
        }

        // Can only edit with an approved request
        return $entry->hasApprovedEditRequest();
    }

    /**
     * Determine if the user can process (approve/reject) edit requests.
     */
    public function processEditRequest(User $user): bool
    {
        $role = $this->role($user);

        return $role && in_array($role, [Role::Vorstand, Role::Admin], true);
    }
}
