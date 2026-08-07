<?php

namespace App\Services;

use App\Enums\Role;
use App\Mail\ProfileContactUpdated;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class ProfileContactUpdateNotifier
{
    /**
     * @param  array<int, string>  $changedContactLabels
     */
    public function notify(
        User $user,
        array $changedContactLabels,
        CarbonInterface $contactChangedAt,
    ): void {
        if ($changedContactLabels === []) {
            return;
        }

        $team = Team::membersTeam();

        if (! $team) {
            Log::warning('Profil-Kontaktaktualisierung ohne Mitglieder-Team.', [
                'user_id' => $user->id,
            ]);

            return;
        }

        $recipients = $team->activeUsers()
            ->wherePivotIn('role', [
                Role::Admin->value,
                Role::Vorstand->value,
                Role::Kassenwart->value,
            ])
            ->whereNotNull('users.email')
            ->pluck('users.email')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($recipients === []) {
            Log::warning('Profil-Kontaktaktualisierung ohne Vorstand-Empfaenger.', [
                'user_id' => $user->id,
            ]);

            return;
        }

        try {
            Mail::to($recipients)->queue(new ProfileContactUpdated(
                $user,
                $changedContactLabels,
                $contactChangedAt,
            ));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
