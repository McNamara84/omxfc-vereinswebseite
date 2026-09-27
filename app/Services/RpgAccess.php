<?php

namespace App\Services;

use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class RpgAccess
{
    public function team(bool $lock = false): ?Team
    {
        $query = Team::query()->where('name', 'AG Rollenspiel')->where('personal_team', false);
        $teams = ($lock ? $query->lockForUpdate() : $query)->get();

        return $teams->count() === 1 ? $teams->first() : null;
    }

    public function isLeader(User $user): bool
    {
        $team = $this->team();

        return $team && $team->user_id === $user->id && $this->isMember($user->id, $team);
    }

    public function isMember(int $userId, ?Team $team = null): bool
    {
        $team ??= $this->team();

        return $team && $team->activeUsers()->where('users.id', $userId)->exists();
    }

    public function requireLeader(User $user, bool $lock = false): Team
    {
        $team = $this->team($lock);
        if (! $team || $team->user_id !== $user->id || ! $this->isMember($user->id, $team)) {
            throw new AuthorizationException('Nur die Leitung der AG Rollenspiel darf diese Aktion ausführen.');
        }

        return $team;
    }
}
