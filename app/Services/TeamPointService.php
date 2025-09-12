<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

class TeamPointService
{
    /**
     * Get the total points of a user in their current team.
     */
    public function getUserPoints(User $user): int
    {
        $team = $user->currentTeam;

        return $team ? $user->totalPointsForTeam($team) : 0;
    }

    /**
     * Ensure the authenticated user has at least the required points.
     *
     * @throws AuthorizationException
     */
    public function assertMinPoints(int $required): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            throw new AuthorizationException('Nicht authentifiziert.');
        }

        if ($this->getUserPoints($user) < $required) {
            throw new AuthorizationException("Mindestens {$required} Baxx erforderlich.");
        }
    }
}

