<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TourAssignmentSource;
use App\Enums\TourAssignmentStatus;
use App\Models\Team;
use App\Models\TourAssignment;
use App\Models\User;
use App\Services\TourAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class TourControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createMember(): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => Role::Mitglied->value]);

        return $user;
    }

    private function createPendingAssignment(User $user): TourAssignment
    {
        return TourAssignment::create([
            'user_id' => $user->id,
            'tour_key' => 'hauptmenue',
            'tour_version' => 1,
            'status' => TourAssignmentStatus::Pending,
            'assigned_via' => TourAssignmentSource::System,
            'assigned_by_user_id' => null,
            'assigned_at' => now(),
            'metadata' => [],
        ]);
    }

    public function test_current_returns_open_tour_payload_for_authenticated_member(): void
    {
        $member = $this->createMember();
        $assignment = $this->createPendingAssignment($member);

        $this->actingAs($member)
            ->getJson(route('touren.current'))
            ->assertOk()
            ->assertJsonPath('tour.assignment_id', $assignment->id)
            ->assertJsonPath('tour.key', 'hauptmenue')
            ->assertJsonPath('tour.status', TourAssignmentStatus::Pending->value)
            ->assertJsonCount(48, 'tour.steps');
    }

    public function test_current_upgrades_outdated_assignment_before_returning_payload(): void
    {
        $member = $this->createMember();
        $staleAssignment = $this->createPendingAssignment($member);

        Config::set('tours.hauptmenue.version', 2);

        $response = $this->actingAs($member)
            ->getJson(route('touren.current'))
            ->assertOk()
            ->assertJsonPath('tour.key', 'hauptmenue')
            ->assertJsonPath('tour.version', 2)
            ->assertJsonPath('tour.status', TourAssignmentStatus::Pending->value);

        $assignmentId = $response->json('tour.assignment_id');

        $this->assertIsInt($assignmentId);
        $this->assertNotSame($staleAssignment->id, $assignmentId);
        $this->assertDatabaseHas('tour_assignments', [
            'id' => $assignmentId,
            'user_id' => $member->id,
            'tour_key' => 'hauptmenue',
            'tour_version' => 2,
            'status' => TourAssignmentStatus::Pending->value,
        ]);

        $this->assertSame(TourAssignmentStatus::Completed, $staleAssignment->fresh()->status);
    }

    public function test_progress_starts_tour_and_updates_current_step(): void
    {
        $member = $this->createMember();
        $assignment = $this->createPendingAssignment($member);

        $this->actingAs($member)
            ->postJson(route('touren.progress', $assignment), ['step_key' => 'profile-settings'])
            ->assertOk()
            ->assertJsonPath('tour.current_step_key', 'profile-settings')
            ->assertJsonPath('tour.status', TourAssignmentStatus::InProgress->value)
            ->assertJsonPath('tour.current_step_index', 47);

        $this->assertDatabaseHas('tour_assignments', [
            'id' => $assignment->id,
            'status' => TourAssignmentStatus::InProgress->value,
            'current_step_key' => 'profile-settings',
        ]);
        $this->assertNotNull($assignment->fresh()->started_at);
    }

    public function test_progress_rejects_step_key_outside_tour_definition(): void
    {
        $member = $this->createMember();
        $assignment = $this->createPendingAssignment($member);

        $this->actingAs($member)
            ->postJson(route('touren.progress', $assignment), ['step_key' => 'ungueltiger-schritt'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['step_key']);

        $this->assertDatabaseHas('tour_assignments', [
            'id' => $assignment->id,
            'status' => TourAssignmentStatus::Pending->value,
            'current_step_key' => null,
        ]);
    }

    public function test_dismiss_suppresses_tour_for_current_session(): void
    {
        $member = $this->createMember();
        $assignment = $this->createPendingAssignment($member);

        $this->actingAs($member)
            ->postJson(route('touren.dismiss', $assignment))
            ->assertOk()
            ->assertJsonPath('suppressed', true);

        $this->actingAs($member)
            ->getJson(route('touren.current'))
            ->assertOk()
            ->assertJsonPath('tour', null);

        $this->assertNotNull($assignment->fresh()->dismissed_at);
    }

    public function test_complete_marks_tour_completed_and_hides_it_from_current_payload(): void
    {
        $member = $this->createMember();
        $assignment = $this->createPendingAssignment($member);

        $this->actingAs($member)
            ->postJson(route('touren.complete', $assignment))
            ->assertOk()
            ->assertJsonPath('completed', true);

        $this->assertDatabaseHas('tour_assignments', [
            'id' => $assignment->id,
            'status' => TourAssignmentStatus::Completed->value,
        ]);

        $this->actingAs($member)
            ->getJson(route('touren.current'))
            ->assertOk()
            ->assertJsonPath('tour', null);
    }

    public function test_member_can_restart_own_tour_from_selfservice(): void
    {
        $member = $this->createMember();
        $assignment = $this->createPendingAssignment($member);

        $assignment->forceFill([
            'status' => TourAssignmentStatus::Completed,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now()->subMinutes(2),
            'current_step_key' => 'profile-settings',
        ])->save();

        $this->actingAs($member)
            ->from(route('profile.show'))
            ->post(route('touren.restart', 'hauptmenue'))
            ->assertRedirect(route('profile.show'));

        $this->assertDatabaseHas('tour_assignments', [
            'id' => $assignment->id,
            'status' => TourAssignmentStatus::Pending->value,
            'assigned_via' => TourAssignmentSource::SelfService->value,
            'current_step_key' => null,
        ]);
    }

    public function test_restart_rejects_unknown_tour_key(): void
    {
        $member = $this->createMember();

        $this->actingAs($member)
            ->postJson(route('touren.restart', 'unbekannt'))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tour_key']);
    }

    public function test_restart_rejects_non_self_service_tour_key(): void
    {
        $member = $this->createMember();
        Config::set('tours.profilpflege.self_service_enabled', false);

        $this->actingAs($member)
            ->postJson(route('touren.restart', 'profilpflege'))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tour_key']);

        $this->assertDatabaseMissing('tour_assignments', [
            'user_id' => $member->id,
            'tour_key' => 'profilpflege',
            'assigned_via' => TourAssignmentSource::SelfService->value,
        ]);
    }

    public function test_member_cannot_control_foreign_assignment(): void
    {
        $owner = $this->createMember();
        $intruder = $this->createMember();
        $assignment = $this->createPendingAssignment($owner);

        $this->actingAs($intruder)
            ->postJson(route('touren.dismiss', $assignment))
            ->assertForbidden();
    }

    public function test_current_ignores_session_suppressed_assignment_until_logout(): void
    {
        $member = $this->createMember();
        $assignment = $this->createPendingAssignment($member);

        $this->actingAs($member)
            ->withSession([TourAssignmentService::SESSION_SUPPRESSION_KEY => [$assignment->id]])
            ->getJson(route('touren.current'))
            ->assertOk()
            ->assertJsonPath('tour', null);
    }
}