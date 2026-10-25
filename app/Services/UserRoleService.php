<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\Memberships\MembershipContext;
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
        $membership = app(MembershipContext::class)->forUserInTeam($user, $team);

        if (! $membership->exists) {
            throw new ModelNotFoundException('Team membership not found.');
        }

        $role = $membership->role;

        if (! $role) {
            throw new ModelNotFoundException('Role not found for user in team.');
        }

        return $role;
    }
}
