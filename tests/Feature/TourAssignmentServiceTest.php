<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TourAssignmentSource;
use App\Enums\TourAssignmentStatus;
use App\Models\Team;
use App\Models\TourAssignment;
use App\Models\User;
use App\Services\Profile\ProfileTourCompletionService;
use App\Services\Profile\SeriesProfileCompletion;
use App\Services\TourAssignmentService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class TourAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_assign_if_missing_recovers_when_another_request_inserts_after_the_lookup(): void
    {
        $member = User::factory()->create();
        $service = app(TourAssignmentService::class);
        $connection = DB::connection();
        $dispatcher = $connection->getEventDispatcher();
        $connection->setEventDispatcher(clone $dispatcher);
        $inserted = false;
        $winner = null;
        try {
            // Reproduce the competing insert after the empty SELECT, before our INSERT.
            $connection->listen(function (QueryExecuted $query) use ($member, $service, &$inserted, &$winner): void {
                if ($inserted || ! str_starts_with($query->sql, 'select ') || ! str_contains($query->sql, 'tour_assignments')) {
                    return;
                }
                $inserted = true;
                $winner = $service->assignIfMissing($member, 'profilpflege', TourAssignmentSource::Manual);
                $winner->forceFill([
                    'status' => TourAssignmentStatus::InProgress,
                    'current_step_key' => 'profile-series-data',
                ])->save();
            });

            $assignment = $service->assignIfMissing($member, 'profilpflege', TourAssignmentSource::System);
        } finally {
            $connection->setEventDispatcher($dispatcher);
        }

        $this->assertTrue($inserted);
        $this->assertSame($winner->id, $assignment->id);
        $this->assertFalse($assignment->wasRecentlyCreated);
        $this->assertSame(TourAssignmentSource::Manual, $assignment->assigned_via);
        $this->assertSame(TourAssignmentStatus::InProgress, $assignment->status);
        $this->assertSame('profile-series-data', $assignment->current_step_key);
        $this->assertSame(1, TourAssignment::where('user_id', $member->id)->where('tour_key', 'profilpflege')->count());
    }

    public function test_profile_overview_exposes_the_central_reward_and_required_field_count(): void
    {
        $user = User::factory()->create();
        $overview = app(TourAssignmentService::class)->selfServiceOverviewForUser($user)
            ->firstWhere('definition.key', 'profilpflege');

        $this->assertSame(ProfileTourCompletionService::POINTS, $overview['reward_points']);
        $this->assertSame(count(SeriesProfileCompletion::FIELDS), $overview['profile_field_count']);
    }

    public function test_unrewarded_completion_and_retry_return_persistable_assignments(): void
    {
        $member = User::factory()->create();
        $service = app(TourAssignmentService::class);
        $assignment = $service->assignIfMissing($member, 'hauptmenue', TourAssignmentSource::System);

        foreach (['first', 'retry'] as $attempt) {
            $result = $service->complete($assignment);
            $this->assertNull($result->reward);
            $this->assertTrue($result->assignment->is($assignment));
            $this->assertSame(TourAssignmentStatus::Completed, $result->assignment->status);
            $this->assertArrayNotHasKey('reward_result', $result->assignment->getAttributes());
            $result->assignment->forceFill(['metadata' => ['completion_attempt' => $attempt]])->save();
            $this->assertSame(['completion_attempt' => $attempt], $assignment->fresh()->metadata);
        }
    }

    public function test_current_promptable_assignment_upgrades_outdated_assignment_to_current_version(): void
    {
        $team = Team::membersTeam();
        $member = User::factory()->create(['current_team_id' => $team->id]);

        $team->users()->attach($member, ['role' => Role::Mitglied->value]);

        $staleAssignment = TourAssignment::create([
            'user_id' => $member->id,
            'tour_key' => 'hauptmenue',
            'tour_version' => 1,
            'status' => TourAssignmentStatus::Pending,
            'assigned_via' => TourAssignmentSource::System,
            'assigned_at' => now()->subDay(),
            'current_step_key' => 'dashboard',
            'metadata' => [],
        ]);

        $currentVersion = (int) config('tours.hauptmenue.version');

        $assignment = app(TourAssignmentService::class)->currentPromptableAssignmentForUser($member);

        $this->assertNotNull($assignment);
        $this->assertSame('hauptmenue', $assignment->tour_key);
        $this->assertSame($currentVersion, $assignment->tour_version);
        $this->assertSame(TourAssignmentStatus::Pending, $assignment->status);
        $this->assertNotSame($staleAssignment->id, $assignment->id);

        $staleAssignment = $staleAssignment->fresh();

        $this->assertSame(TourAssignmentStatus::Completed, $staleAssignment->status);
        $this->assertSame($currentVersion, $staleAssignment->metadata['superseded_by_version'] ?? null);
    }

    public function test_profile_campaign_invitation_takes_priority_over_older_navigation_tour(): void
    {
        $team = Team::membersTeam();
        $member = User::factory()->create(['current_team_id' => $team->id]);

        $team->users()->attach($member, ['role' => Role::Mitglied->value]);

        $first = TourAssignment::create([
            'user_id' => $member->id,
            'tour_key' => 'hauptmenue',
            'tour_version' => (int) config('tours.hauptmenue.version'),
            'status' => TourAssignmentStatus::Pending,
            'assigned_via' => TourAssignmentSource::System,
            'assigned_at' => now(),
            'metadata' => [],
        ]);

        TourAssignment::create([
            'user_id' => $member->id,
            'tour_key' => 'profilpflege',
            'tour_version' => (int) config('tours.profilpflege.version'),
            'status' => TourAssignmentStatus::Pending,
            'assigned_via' => TourAssignmentSource::System,
            'assigned_at' => $first->assigned_at,
            'metadata' => [],
        ]);

        $assignment = app(TourAssignmentService::class)->currentPromptableAssignmentForUser($member);

        $this->assertNotNull($assignment);
        $this->assertSame('profilpflege', $assignment->tour_key);
    }

    public function test_reassign_resets_existing_assignment_state(): void
    {
        $team = Team::membersTeam();
        $admin = User::factory()->create(['current_team_id' => $team->id]);
        $member = User::factory()->create(['current_team_id' => $team->id]);

        $team->users()->attach($admin, ['role' => Role::Admin->value]);
        $team->users()->attach($member, ['role' => Role::Mitglied->value]);

        TourAssignment::create([
            'user_id' => $member->id,
            'tour_key' => 'hauptmenue',
            'tour_version' => (int) config('tours.hauptmenue.version'),
            'status' => TourAssignmentStatus::Completed,
            'assigned_via' => TourAssignmentSource::System,
            'assigned_by_user_id' => null,
            'assigned_at' => now()->subDays(3),
            'started_at' => now()->subDays(3),
            'completed_at' => now()->subDays(2),
            'dismissed_at' => now()->subDays(2),
            'next_prompt_at' => now()->subDay(),
            'current_step_key' => 'profil',
            'metadata' => ['completed_step_count' => 4],
        ]);

        $assignment = app(TourAssignmentService::class)->reassign(
            user: $member,
            tourKey: 'hauptmenue',
            source: TourAssignmentSource::Manual,
            assignedBy: $admin,
        );

        $this->assertSame(TourAssignmentStatus::Pending, $assignment->status);
        $this->assertSame(TourAssignmentSource::Manual, $assignment->assigned_via);
        $this->assertSame($admin->id, $assignment->assigned_by_user_id);
        $this->assertNull($assignment->started_at);
        $this->assertNull($assignment->completed_at);
        $this->assertNull($assignment->dismissed_at);
        $this->assertNull($assignment->next_prompt_at);
        $this->assertNull($assignment->current_step_key);
        $this->assertSame([], $assignment->metadata);
    }

    #[TestWith([null, true])]
    #[TestWith([null, false])]
    #[TestWith([Role::Anwaerter, true])]
    #[TestWith([Role::Anwaerter, false])]
    public function test_ineligible_profile_tours_are_excluded_before_selection_and_upgrade(?Role $role, bool $outdated): void
    {
        $team = Team::membersTeam();
        $member = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($member, ['role' => Role::Mitglied->value]);
        $service = app(TourAssignmentService::class);
        $profile = $service->assignIfMissing($member, 'profilpflege', TourAssignmentSource::System);
        $status = $outdated ? TourAssignmentStatus::Pending : TourAssignmentStatus::InProgress;
        $profile->update(['tour_version' => $outdated ? 2 : config('tours.profilpflege.version'), 'status' => $status]);
        $menu = $service->assignIfMissing($member, 'hauptmenue', TourAssignmentSource::System);

        $team->users()->detach($member);
        if ($role !== null) {
            $team->users()->attach($member, ['role' => $role->value]);
        }
        // A privileged role in another active team does not confer membership.
        $otherTeam = Team::factory()->create(['name' => 'AG Test', 'personal_team' => false]);
        $otherTeam->users()->attach($member, ['role' => Role::Admin->value]);
        $member->update(['current_team_id' => $otherTeam->id]);

        $this->assertSame($menu->id, $service->currentPromptableAssignmentForUser($member)?->id);
        $this->assertNull($service->currentPromptableAssignmentForUser($member, [$menu->id]));
        $this->assertSame($status, $profile->fresh()->status);
        $this->assertSame(1, TourAssignment::where('user_id', $member->id)->where('tour_key', 'profilpflege')->count());

        $team->users()->syncWithoutDetaching([$member->id => ['role' => Role::Mitglied->value]]);
        $this->assertSame('profilpflege', $service->currentPromptableAssignmentForUser($member)?->tour_key);
    }
}
