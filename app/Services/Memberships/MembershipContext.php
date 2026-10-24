<?php

namespace App\Services\Memberships;

use App\Enums\Role;
use App\Models\Membership;
use App\Models\Team;
use App\Models\User;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Http\Request;
use WeakMap;

/** Request-local membership data; policies and locked decisions remain with their callers. */
final class MembershipContext
{
    /** @var array<string, MembershipSnapshot> */
    private array $snapshots = [];

    /** @var array<string, bool> */
    private array $stringMatches = [];

    /** @var WeakMap<User|Team, null> */
    private WeakMap $models;

    private int $freshDepth = 0;

    private ?Team $membersTeam = null;

    public function __construct()
    {
        $this->models = new WeakMap;
    }

    public function forUserInTeam(User $user, Team $team): MembershipSnapshot
    {
        $this->track($user, $team);
        $connection = $team->getConnection();
        if ($this->readsFresh($connection)) {
            return $this->freshForUserInTeam($user, $team);
        }

        $key = $this->key($connection, $user->id, $team->id);

        return $this->snapshots[$key] ??= $this->load($user, $team);
    }

    public function freshForUserInTeam(User $user, Team $team): MembershipSnapshot
    {
        $this->track($user, $team);
        $this->snapshots = [];
        $this->stringMatches = [];

        return $this->load($user, $team);
    }

    public function roleInCurrentTeam(User $user): ?Role
    {
        $this->track($user);
        $team = $user->currentTeam;

        return $team ? $this->forUserInTeam($user, $team)->role : null;
    }

    public function roleInMembersTeam(User $user): ?Role
    {
        $this->track($user);
        $team = $this->membersTeam ??= Team::membersTeam();

        return $team ? $this->forUserInTeam($user, $team)->role : null;
    }

    /** @return array{current: ?Role, members: ?Role} */
    public function navigationRoles(User $user): array
    {
        $this->track($user);
        $fresh = $this->readsFresh($user->getConnection());
        $fresh ? $user->load('teams') : $user->loadMissing('teams');
        $membersTeamId = $user->teams->firstWhere('name', 'Mitglieder')?->id;
        $roles = ['current' => null, 'members' => null];
        foreach ($user->teams as $team) {
            $this->track($team);
            $snapshot = new MembershipSnapshot((int) $user->id, (int) $team->id, true, $team->membership?->role);
            if (! $fresh) {
                $key = $this->key($team->getConnection(), $snapshot->userId, $snapshot->teamId);
                $snapshot = $this->snapshots[$key] ??= $snapshot;
            }
            if ((int) $team->id === (int) $user->current_team_id) {
                $roles['current'] = $snapshot->role;
            }
            if ($team->id === $membersTeamId) {
                $roles['members'] = $snapshot->role;
            }
        }

        return $roles;
    }

    public function hasNamedTeam(User $user, string $name, bool $owned = false): bool
    {
        $this->track($user);
        $key = $this->key($user->getConnection(), (int) $user->id, 0).':named:'.(int) $owned.':'.base64_encode($name);
        $query = fn (): bool => ($owned ? $user->ownedTeams() : $user->teams())->where('name', $name)->exists();
        if ($this->readsFresh($user->getConnection())) {
            return $query();
        }

        return $this->stringMatches[$key] ??= $query();
    }

    /** Keep SQL collation behavior for strings outside the canonical enum values. */
    public function hasRawRole(User $user, Team $team, string $role): bool
    {
        $snapshot = $this->forUserInTeam($user, $team);
        if (! $snapshot->exists || $snapshot->userExists === false) {
            return false;
        }
        $expected = Role::tryFrom($role);
        if ($snapshot->userExists === true && $snapshot->role !== null && $expected !== null) {
            return $snapshot->role === $expected;
        }

        $key = $this->key($team->getConnection(), $snapshot->userId, $snapshot->teamId).':'.base64_encode($role);
        $query = fn (): bool => $team->users()->wherePivot('role', $role)->where('user_id', $user->id)->exists();
        if ($this->readsFresh($team->getConnection())) {
            return $query();
        }

        return $this->stringMatches[$key] ??= $query();
    }

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public function runFresh(Closure $callback): mixed
    {
        $this->reset();
        $this->freshDepth++;
        try {
            return $callback();
        } finally {
            $this->freshDepth--;
            $this->reset();
        }
    }

    public function invalidateUser(int $userId, ?User $changed = null): void
    {
        if ($changed && $changed->wasChanged('current_team_id')) {
            $this->restoreAfterRollback($changed, ['current_team_id']);
        }
        // Relations may be held by several model instances, including Auth's user.
        // Clear the small request-local set rather than retaining an instance registry by ID.
        foreach ($this->models as $model => $_) {
            if ($changed && $model !== $changed && $model instanceof User && $model->id === $userId
                && $model->getConnection()->getName() === $changed->getConnection()->getName()
                && $changed->wasChanged('current_team_id')) {
                $model->setAttribute('current_team_id', $changed->current_team_id);
                $model->syncOriginalAttribute('current_team_id');
            }
        }
        $this->reset();
    }

    public function invalidateTeam(int $teamId, ?Team $changed = null): void
    {
        if ($changed && $changed->wasChanged(['name', 'user_id', 'personal_team'])) {
            $this->restoreAfterRollback($changed, ['name', 'user_id', 'personal_team']);
        }
        foreach ($this->models as $model => $_) {
            if ($changed && $model !== $changed && $model instanceof Team && $model->id === $teamId
                && $model->getConnection()->getName() === $changed->getConnection()->getName()) {
                foreach (['name', 'user_id', 'personal_team'] as $attribute) {
                    if ($changed->wasChanged($attribute)) {
                        $model->setAttribute($attribute, $changed->getAttribute($attribute));
                        $model->syncOriginalAttribute($attribute);
                    }
                }
            }
        }
        $this->reset();
    }

    public function reset(): void
    {
        $this->membersTeam = null;
        $this->snapshots = [];
        $this->stringMatches = [];
        foreach ($this->models as $model => $_) {
            foreach ($model instanceof User ? ['teams', 'ownedTeams', 'currentTeam'] : ['users', 'owner'] as $relation) {
                $model->unsetRelation($relation);
            }
        }
    }

    public function track(User|Team ...$models): void
    {
        foreach ($models as $model) {
            $this->models[$model] = null;
        }
    }

    private function key(Connection $connection, int $userId, int $teamId): string
    {
        return $connection->getName().':'.$connection->getDatabaseName().':'.spl_object_id($connection).":{$userId}:{$teamId}";
    }

    private function readsFresh(Connection $connection): bool
    {
        // Livewire's component test broker bypasses HTTP middleware; still honor write boundaries.
        return $this->freshDepth > 0 || $connection->transactionLevel() > 0
            || ! app(Request::class)->isMethodSafe();
    }

    /** @param list<string> $attributes */
    private function restoreAfterRollback(User|Team $changed, array $attributes): void
    {
        $connection = $changed->getConnection();
        if ($connection->transactionLevel() === 0) {
            return;
        }
        $connection->afterRollBack(function () use ($changed, $connection, $attributes): void {
            $row = $connection->table($changed->getTable())->where('id', $changed->id)->first($attributes);
            if ($row) {
                foreach ($this->models as $model => $_) {
                    if ($model->getTable() === $changed->getTable() && $model->id === $changed->id
                        && $model->getConnection()->getName() === $connection->getName()) {
                        foreach ($attributes as $attribute) {
                            $model->setAttribute($attribute, $row->{$attribute});
                            $model->syncOriginalAttribute($attribute);
                        }
                    }
                }
            }
            $this->reset();
        });
    }

    private function load(User $user, Team $team): MembershipSnapshot
    {
        /** @var Membership|null $membership */
        $membership = Membership::on($team->getConnectionName())
            ->leftJoin('users', 'users.id', '=', 'team_user.user_id')
            ->where('team_user.team_id', $team->id)->where('team_user.user_id', $user->id)
            ->first(['team_user.user_id', 'team_user.team_id', 'team_user.role', 'users.id as existing_user_id']);

        return new MembershipSnapshot((int) $user->id, (int) $team->id, $membership !== null, $membership?->role, $membership?->getAttribute('existing_user_id') !== null);
    }
}
