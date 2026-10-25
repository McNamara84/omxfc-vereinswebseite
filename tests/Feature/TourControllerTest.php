<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TourAssignmentSource;
use App\Enums\TourAssignmentStatus;
use App\Models\Team;
use App\Models\TourAssignment;
use App\Models\User;
use App\Services\TourAssignmentService;
use App\Services\TourRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\TestWith;
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

    private function createPendingAssignment(User $user, ?int $tourVersion = null): TourAssignment
    {
        return TourAssignment::create([
            'user_id' => $user->id,
            'tour_key' => 'hauptmenue',
            'tour_version' => $tourVersion ?? (int) config('tours.hauptmenue.version'),
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
            ->assertJsonCount(count(app(TourRegistry::class)->definition('hauptmenue')->steps), 'tour.steps');
    }

    public function test_former_members_do_not_receive_profile_invitations_and_cannot_mutate_them(): void
    {
        $member = $this->createMember();
        $profile = app(TourAssignmentService::class)->assignIfMissing($member, 'profilpflege', TourAssignmentSource::System);
        $menu = $this->createPendingAssignment($member);
        $this->actingAs($member)->getJson(route('touren.current'))->assertOk()
            ->assertJsonPath('tour.assignment_id', $profile->id);

        Team::membersTeam()->users()->detach($member);

        $this->getJson(route('touren.current'))->assertOk()->assertJsonPath('tour.assignment_id', $menu->id);
        foreach (['start', 'dismiss', 'resume', 'progress', 'complete'] as $action) {
            $this->postJson(route('touren.'.$action, $profile), ['step_key' => 'profile-complete'])->assertForbidden();
        }
        $this->assertSame(TourAssignmentStatus::Pending, $profile->fresh()->status);
        $this->assertNull($profile->fresh()->dismissed_at);
    }

    public function test_current_conditionally_includes_cover_ratings_navigation_step(): void
    {
        config(['cover-ratings.enabled' => true]);
        $member = $this->createMember();
        $assignment = $this->createPendingAssignment($member);

        $response = $this->actingAs($member)
            ->getJson(route('touren.current'))
            ->assertOk()
            ->assertJsonPath('tour.assignment_id', $assignment->id)
            ->assertJsonCount(37, 'tour.steps');

        $coverStep = collect($response->json('tour.steps'))
            ->firstWhere('key', 'community-cover-ratings');

        $this->assertSame('Cover-Bewertungen', $coverStep['title'] ?? null);
        $this->assertArrayNotHasKey('config_enabled', $coverStep ?? []);
    }

    public function test_current_upgrades_outdated_assignment_before_returning_payload(): void
    {
        $member = $this->createMember();
        $staleAssignment = $this->createPendingAssignment($member, 1);
        $currentVersion = (int) config('tours.hauptmenue.version');

        $response = $this->actingAs($member)
            ->getJson(route('touren.current'))
            ->assertOk()
            ->assertJsonPath('tour.key', 'hauptmenue')
            ->assertJsonPath('tour.version', $currentVersion)
            ->assertJsonPath('tour.status', TourAssignmentStatus::Pending->value);

        $assignmentId = $response->json('tour.assignment_id');

        $this->assertIsInt($assignmentId);
        $this->assertNotSame($staleAssignment->id, $assignmentId);
        $this->assertDatabaseHas('tour_assignments', [
            'id' => $assignmentId,
            'user_id' => $member->id,
            'tour_key' => 'hauptmenue',
            'tour_version' => $currentVersion,
            'status' => TourAssignmentStatus::Pending->value,
        ]);

        $this->assertSame(TourAssignmentStatus::Completed, $staleAssignment->fresh()->status);
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function test_bildersuche_step_is_available_only_when_enabled_after_upgrading_the_previous_tour(bool $enabled): void
    {
        config(['bildersuche.enabled' => $enabled, 'cover-ratings.enabled' => true]);
        $member = $this->createMember();
        $previous = $this->createPendingAssignment($member, 7);
        $response = $this->actingAs($member)->getJson(route('touren.current'))->assertOk()
            ->assertJsonPath('tour.version', 8)->assertJsonCount($enabled ? 38 : 37, 'tour.steps');
        $this->assertSame(8, $previous->fresh()->metadata['superseded_by_version']);
        $steps = collect($response->json('tour.steps'));
        $this->assertSame($enabled, $steps->contains('key', 'content-bildersuche'));
        $progress = $this->postJson(route('touren.progress', $response->json('tour.assignment_id')), ['step_key' => 'content-bildersuche']);
        if ($enabled) {
            $this->assertArrayNotHasKey('config_enabled', $steps->firstWhere('key', 'content-bildersuche'));
            $progress->assertOk()->assertJsonPath('tour.current_step_key', 'content-bildersuche');
        } else {
            $progress->assertUnprocessable()->assertJsonValidationErrors(['step_key']);
        }
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
            ->assertJsonPath('tour.current_step_index', collect(app(TourRegistry::class)->definition('hauptmenue')->steps)->search(fn ($step) => $step['key'] === 'profile-settings'));

        $this->assertDatabaseHas('tour_assignments', [
            'id' => $assignment->id,
            'status' => TourAssignmentStatus::InProgress->value,
            'current_step_key' => 'profile-settings',
        ]);
        $this->assertNotNull($assignment->fresh()->started_at);
    }

    public function test_previous_navigation_tour_upgrades_and_accepts_character_step_progress(): void
    {
        $member = $this->createMember();
        $rpg = Team::factory()->create(['name' => 'AG Rollenspiel', 'personal_team' => false]);
        $rpg->users()->attach($member, ['role' => Role::Mitglied->value]);
        $previous = $this->createPendingAssignment($member, 5);
        $response = $this->actingAs($member)
            ->getJson(route('touren.current'))
            ->assertOk()
            ->assertJsonPath('tour.version', 8);
        $steps = collect($response->json('tour.steps'));
        $this->assertSame(1, $steps->where('key', 'teams-characters')->count());
        $this->assertSame(8, $previous->fresh()->metadata['superseded_by_version']);
        $this->assertNotSame($previous->id, $response->json('tour.assignment_id'));

        $this->postJson(route('touren.progress', $response->json('tour.assignment_id')), ['step_key' => 'teams-characters'])
            ->assertOk()
            ->assertJsonPath('tour.current_step_key', 'teams-characters')
            ->assertJsonPath('tour.current_step_index', $steps->search(fn ($step) => $step['key'] === 'teams-characters'));
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
            ->assertJsonPath('completed', true)
            ->assertJsonPath('reward', null);

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

    #[TestWith([null])]
    #[TestWith([1])]
    public function test_resume_rejects_other_tours_without_mutating_assignments_or_session(?int $version): void
    {
        $member = $this->createMember();
        $menu = $this->createPendingAssignment($member, $version);
        $profile = app(TourAssignmentService::class)->assignIfMissing($member, 'profilpflege', TourAssignmentSource::System);
        $before = $menu->fresh()->getAttributes();

        $this->actingAs($member)->withSession([
            TourAssignmentService::SESSION_ACTIVE_KEY => $profile->id,
            TourAssignmentService::SESSION_SUPPRESSION_KEY => [$menu->id],
        ])->post(route('touren.resume', $menu))->assertNotFound()
            ->assertSessionHas(TourAssignmentService::SESSION_ACTIVE_KEY, $profile->id)
            ->assertSessionHas(TourAssignmentService::SESSION_SUPPRESSION_KEY, [$menu->id]);

        $this->assertSame($before, $menu->fresh()->getAttributes());
        $this->assertSame(1, TourAssignment::where('user_id', $member->id)->where('tour_key', 'hauptmenue')->count());
    }

    public function test_resume_rejects_another_members_profile_tour(): void
    {
        $owner = $this->createMember();
        $profile = app(TourAssignmentService::class)->assignIfMissing($owner, 'profilpflege', TourAssignmentSource::System);

        $this->actingAs($this->createMember())->post(route('touren.resume', $profile))->assertForbidden();
        $this->assertNull($profile->fresh()->started_at);
    }

    #[TestWith([null, false])]
    #[TestWith([null, true])]
    #[TestWith([Role::Anwaerter, false])]
    #[TestWith([Role::Anwaerter, true])]
    public function test_ineligible_users_cannot_create_or_reset_profile_tours(?Role $role, bool $existing): void
    {
        $member = $this->createMember();
        $profile = $existing
            ? app(TourAssignmentService::class)->assignIfMissing($member, 'profilpflege', TourAssignmentSource::System)
            : null;
        $profile?->forceFill(['status' => TourAssignmentStatus::Completed, 'completed_at' => now()])->save();
        $before = $profile?->fresh()->getAttributes();
        if ($role === null) {
            Team::membersTeam()->users()->detach($member);
        } else {
            Team::membersTeam()->users()->updateExistingPivot($member->id, ['role' => $role->value]);
        }
        // A privileged role in another selected team must not grant eligibility.
        $otherTeam = Team::factory()->create(['name' => 'AG Test', 'personal_team' => false]);
        $otherTeam->users()->attach($member, ['role' => Role::Admin->value]);
        $member->forceFill(['current_team_id' => $otherTeam->id])->save();
        $suppressed = $profile ? [$profile->id] : [];

        $this->actingAs($member->refresh())->withSession([
            TourAssignmentService::SESSION_ACTIVE_KEY => 999,
            TourAssignmentService::SESSION_SUPPRESSION_KEY => $suppressed,
        ])->post(route('touren.restart', 'profilpflege'))->assertForbidden()
            ->assertSessionHas(TourAssignmentService::SESSION_ACTIVE_KEY, 999)
            ->assertSessionHas(TourAssignmentService::SESSION_SUPPRESSION_KEY, $suppressed);

        $this->assertSame($existing ? 1 : 0, TourAssignment::where('user_id', $member->id)->where('tour_key', 'profilpflege')->count());
        $this->assertSame($before, $profile?->fresh()->getAttributes());
        $this->from(route('profile.show'))->post(route('touren.restart', 'hauptmenue'))->assertRedirect(route('profile.show'));
    }

    #[TestWith([Role::Mitwirkender])]
    #[TestWith([Role::Mitglied])]
    #[TestWith([Role::Ehrenmitglied])]
    #[TestWith([Role::Kassenwart])]
    #[TestWith([Role::Vorstand])]
    #[TestWith([Role::Admin])]
    public function test_eligible_member_roles_can_restart_the_profile_tour(Role $role): void
    {
        $member = $this->createMember();
        Team::membersTeam()->users()->updateExistingPivot($member->id, ['role' => $role->value]);

        $this->actingAs($member->refresh())->from(route('profile.show'))
            ->post(route('touren.restart', 'profilpflege'))->assertRedirect(route('profile.show'));

        $this->assertDatabaseHas('tour_assignments', [
            'user_id' => $member->id, 'tour_key' => 'profilpflege',
            'status' => TourAssignmentStatus::Pending->value,
            'assigned_via' => TourAssignmentSource::SelfService->value,
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
