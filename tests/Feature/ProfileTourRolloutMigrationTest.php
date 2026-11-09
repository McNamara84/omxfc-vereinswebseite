<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TourAssignmentSource;
use App\Enums\TourAssignmentStatus;
use App\Models\Team;
use App\Models\TourAssignment;
use App\Models\User;
use App\Services\TourAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('rollout assigns all active roles once and preserves current progress', function (): void {
    $team = Team::membersTeam();
    $members = [];
    foreach (Role::cases() as $role) {
        $member = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($member, ['role' => $role->value]);
        $members[$role->value] = $member;
    }
    $outside = User::factory()->create();
    $ag = Team::factory()->create();
    $ag->users()->attach($outside, ['role' => Role::Admin->value]);
    $existing = app(TourAssignmentService::class)->assignIfMissing($members[Role::Mitglied->value], 'profilpflege', TourAssignmentSource::Manual);
    $existing->forceFill(['status' => TourAssignmentStatus::InProgress, 'current_step_key' => 'profile-field-lesestand', 'started_at' => now()])->save();
    $original = $existing->fresh()->getAttributes();
    $old = $existing->replicate();
    $old->forceFill(['tour_version' => 2, 'metadata' => ['history' => 'keep']])->save();
    $completed = $existing->replicate();
    $completed->forceFill(['tour_version' => 1, 'status' => TourAssignmentStatus::Completed, 'completed_at' => now()->subMonth()])->save();
    $completedOriginal = $completed->fresh()->getAttributes();
    $migration = require database_path('migrations/2026_10_25_120001_assign_profile_tour_to_existing_members.php');
    $migration->up();
    $migration->up();
    $migration->down();
    foreach ($members as $role => $member) {
        expect(TourAssignment::where('user_id', $member->id)->where('tour_key', 'profilpflege')->where('tour_version', 3)->count())->toBe($role === Role::Anwaerter->value ? 0 : 1);
    }
    $this->assertDatabaseMissing('tour_assignments', ['user_id' => $outside->id]);
    expect($existing->fresh()->getAttributes())->toBe($original)
        ->and($completed->fresh()->getAttributes())->toBe($completedOriginal)
        ->and($old->fresh()->metadata['superseded_by_version'])->toBe(3)
        ->and($old->fresh()->metadata['history'])->toBe('keep');
    $this->assertDatabaseCount('tour_reward_grants', 0);
});

test('fresh install without members team is safe', function (): void {
    DB::table('teams')->where('name', 'Mitglieder')->update(['name' => 'Other']);
    $count = TourAssignment::count();
    $migration = require database_path('migrations/2026_10_25_120001_assign_profile_tour_to_existing_members.php');
    $migration->up();
    expect(TourAssignment::count())->toBe($count);
});

test('new members still receive current profile tour', function (): void {
    $member = User::factory()->create();
    app(TourAssignmentService::class)->assignAutoToursForApprovedMember($member);
    $this->assertDatabaseHas('tour_assignments', ['user_id' => $member->id, 'tour_key' => 'profilpflege', 'tour_version' => 3, 'status' => 'pending']);
    $this->assertDatabaseCount('tour_reward_grants', 0);
});
