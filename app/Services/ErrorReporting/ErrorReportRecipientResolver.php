<?php

namespace App\Services\ErrorReporting;

use App\Enums\Role;
use App\Models\Team;

class ErrorReportRecipientResolver
{
    /**
     * @return array<int, string>
     */
    public function resolve(): array
    {
        $team = Team::membersTeam();

        if (! $team) {
            return [];
        }

        return $team->activeUsers()
            ->wherePivot('role', Role::Admin->value)
            ->whereNotNull('users.email')
            ->pluck('users.email')
            ->filter(fn (mixed $email): bool => is_string($email) && filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false)
            ->map(fn (string $email): string => trim($email))
            ->unique(fn (string $email): string => mb_strtolower($email))
            ->values()
            ->all();
    }
}
