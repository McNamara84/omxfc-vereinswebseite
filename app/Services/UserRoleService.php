<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Membership;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UserRoleService
{
    /**
     * Determine the role of a user within a given team.
     *
     * @throws ModelNotFoundException if the user is not a member of the team.
     */
    public function getRole(User $user, Team $team): Role
    {
        $membership = Membership::where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $membership) {
            throw new ModelNotFoundException('Team membership not found.');
        }

        $role = Role::tryFrom($membership->role);

        if (! $role) {
            throw new ModelNotFoundException('Role not found for user in team.');
        }

        return $role;
    }
}

