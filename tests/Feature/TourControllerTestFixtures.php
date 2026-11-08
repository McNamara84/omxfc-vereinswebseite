<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TourAssignmentSource;
use App\Enums\TourAssignmentStatus;
use App\Models\Team;
use App\Models\TourAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait TourControllerTestFixtures
{
    use RefreshDatabase;

    private function createMember(): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => Role::Mitglied->value]);

        return $user;
    }

    private function createPendingAssignment(User $user, ?int $tourVersion = null): TourAssignment
    {
        return TourAssignment::create(['user_id' => $user->id, 'tour_key' => 'hauptmenue', 'tour_version' => $tourVersion ?? (int) config('tours.hauptmenue.version'), 'status' => TourAssignmentStatus::Pending, 'assigned_via' => TourAssignmentSource::System, 'assigned_by_user_id' => null, 'assigned_at' => now(), 'metadata' => []]);
    }
}
