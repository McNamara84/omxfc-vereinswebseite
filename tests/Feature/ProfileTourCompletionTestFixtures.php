<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TourAssignmentSource;
use App\Models\Team;
use App\Models\TourAssignment;
use App\Models\User;
use App\Services\Profile\SeriesProfileCompletion;
use App\Services\TourAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait ProfileTourCompletionTestFixtures
{
    use RefreshDatabase;

    private function member(bool $complete = true): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => Role::Mitglied->value]);
        if ($complete) {
            $user->forceFill(array_fill_keys(array_keys(SeriesProfileCompletion::FIELDS), 'Gespeicherter Favorit'))->save();
        }

        return $user;
    }

    private function assignment(User $user, bool $started = true): TourAssignment
    {
        $service = app(TourAssignmentService::class);
        $assignment = $service->assignIfMissing($user, 'profilpflege', TourAssignmentSource::System);
        if ($started) {
            $assignment = $service->start($assignment);
            $assignment = $service->rememberProgress($assignment, 'profile-complete');
        }

        return $assignment;
    }
}
