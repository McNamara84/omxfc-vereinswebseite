<?php

namespace App\Services;

use App\Models\Membership;
use App\Models\Team;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Serializes Mitglieder-Team membership decisions with their mutations.
 *
 * Every caller uses the same lock order: Mitglieder-Team, users ordered by
 * primary key, then their team_user rows ordered by user ID.
 */
final class MembersTeamMembershipLock
{
    /**
     * @template TResult
     *
     * @param  array<int, int>  $userIds
     * @param  Closure(LockedMembersTeamMemberships): TResult  $callback
     * @return TResult
     */
    public function run(array $userIds, Closure $callback, int $attempts = 3): mixed
    {
        $userIds = collect($userIds)
            ->map(static fn (int|string $userId): int => (int) $userId)
            ->filter(static fn (int $userId): bool => $userId > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($userIds === []) {
            throw new LogicException('Mindestens ein Nutzer muss gesperrt werden.');
        }

        return DB::transaction(function () use ($callback, $userIds): mixed {
            $team = Team::query()
                ->where('name', 'Mitglieder')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->first(static fn (Team $candidate): bool => $candidate->name === 'Mitglieder');

            if (! $team || $team->name !== 'Mitglieder') {
                throw new LogicException('Das Mitglieder-Team fehlt.');
            }

            $users = User::query()
                ->whereKey($userIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($users->count() !== count($userIds)) {
                throw new LogicException('Mindestens ein zu sperrender Nutzer fehlt.');
            }

            $memberships = Membership::query()
                ->where('team_id', $team->id)
                ->whereIn('user_id', $userIds)
                ->orderBy('user_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('user_id');

            return $callback(new LockedMembersTeamMemberships(
                team: $team,
                users: $users,
                memberships: $memberships,
            ));
        }, $attempts);
    }
}
