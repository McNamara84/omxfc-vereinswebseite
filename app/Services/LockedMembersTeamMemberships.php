<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Membership;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use LogicException;

/**
 * Immutable view of users and their Mitglieder-Team pivots while the
 * corresponding database rows are locked by MembersTeamMembershipLock.
 */
final readonly class LockedMembersTeamMemberships
{
    /**
     * @param  Collection<int, User>  $users
     * @param  Collection<int, Membership>  $memberships
     */
    public function __construct(
        public Team $team,
        private Collection $users,
        private Collection $memberships,
    ) {}

    public function user(int $userId): User
    {
        $user = $this->users->get($userId);

        if (! $user instanceof User) {
            throw new LogicException("Der gesperrte Nutzer {$userId} fehlt.");
        }

        return $user;
    }

    public function role(int $userId): ?Role
    {
        $membership = $this->memberships->get($userId);

        if (! $membership instanceof Membership) {
            return null;
        }

        return Role::tryFrom((string) $membership->role);
    }

    public function isActiveMember(int $userId): bool
    {
        $role = $this->role($userId);

        return $role !== null && $role !== Role::Anwaerter;
    }

    public function hasRole(int $userId, Role ...$roles): bool
    {
        $role = $this->role($userId);

        return $role !== null && in_array($role, $roles, true);
    }
}
