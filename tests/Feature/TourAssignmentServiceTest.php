<?php

declare(strict_types=1);

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
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('assign if missing recovers when another request inserts after the lookup', function (): void {
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
            $winner->forceFill(['status' => TourAssignmentStatus::InProgress, 'current_step_key' => 'profile-series-data'])->save();
        });
        $assignment = $service->assignIfMissing($member, 'profilpflege', TourAssignmentSource::System);
    } finally {
        $connection->setEventDispatcher($dispatcher);
    }
    expect($inserted)->toBeTrue()
        ->and($assignment->id)->toBe($winner->id)
        ->and($assignment->wasRecentlyCreated)->toBeFalse()
        ->and($assignment->assigned_via)->toBe(TourAssignmentSource::Manual)
        ->and($assignment->status)->toBe(TourAssignmentStatus::InProgress)
        ->and($assignment->current_step_key)->toBe('profile-series-data')
        ->and(TourAssignment::where('user_id', $member->id)->where('tour_key', 'profilpflege')->count())->toBe(1);
});

test('profile overview exposes the central reward and required field count', function (): void {
    $user = User::factory()->create();
    $overview = app(TourAssignmentService::class)->selfServiceOverviewForUser($user)->firstWhere('definition.key', 'profilpflege');
    expect($overview['reward_points'])->toBe(ProfileTourCompletionService::POINTS)
        ->and($overview['profile_field_count'])->toBe(count(SeriesProfileCompletion::FIELDS));
});

test('unrewarded completion and retry return persistable assignments', function (): void {
    $member = User::factory()->create();
    $service = app(TourAssignmentService::class);
    $assignment = $service->assignIfMissing($member, 'hauptmenue', TourAssignmentSource::System);
    foreach (['first', 'retry'] as $attempt) {
        $result = $service->complete($assignment);
        expect($result->reward)->toBeNull()
            ->and($result->assignment->is($assignment))->toBeTrue()
            ->and($result->assignment->status)->toBe(TourAssignmentStatus::Completed)
            ->and($result->assignment->getAttributes())->not->toHaveKey('reward_result');
        $result->assignment->forceFill(['metadata' => ['completion_attempt' => $attempt]])->save();
        expect($assignment->fresh()->metadata)->toBe(['completion_attempt' => $attempt]);
    }
});

test('current promptable assignment upgrades outdated assignment to current version', function (): void {
    $team = Team::membersTeam();
    $member = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($member, ['role' => Role::Mitglied->value]);
    $staleAssignment = TourAssignment::create(['user_id' => $member->id, 'tour_key' => 'hauptmenue', 'tour_version' => 1, 'status' => TourAssignmentStatus::Pending, 'assigned_via' => TourAssignmentSource::System, 'assigned_at' => now()->subDay(), 'current_step_key' => 'dashboard', 'metadata' => []]);
    $currentVersion = (int) config('tours.hauptmenue.version');
    $assignment = app(TourAssignmentService::class)->currentPromptableAssignmentForUser($member);
    expect($assignment)->not->toBeNull()
        ->and($assignment->tour_key)->toBe('hauptmenue')
        ->and($assignment->tour_version)->toBe($currentVersion)
        ->and($assignment->status)->toBe(TourAssignmentStatus::Pending)
        ->and($assignment->id)->not->toBe($staleAssignment->id);
    $staleAssignment = $staleAssignment->fresh();
    expect($staleAssignment->status)->toBe(TourAssignmentStatus::Completed)
        ->and($staleAssignment->metadata['superseded_by_version'] ?? null)->toBe($currentVersion);
});

test('profile campaign invitation takes priority over older navigation tour', function (): void {
    $team = Team::membersTeam();
    $member = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($member, ['role' => Role::Mitglied->value]);
    $first = TourAssignment::create(['user_id' => $member->id, 'tour_key' => 'hauptmenue', 'tour_version' => (int) config('tours.hauptmenue.version'), 'status' => TourAssignmentStatus::Pending, 'assigned_via' => TourAssignmentSource::System, 'assigned_at' => now(), 'metadata' => []]);
    TourAssignment::create(['user_id' => $member->id, 'tour_key' => 'profilpflege', 'tour_version' => (int) config('tours.profilpflege.version'), 'status' => TourAssignmentStatus::Pending, 'assigned_via' => TourAssignmentSource::System, 'assigned_at' => $first->assigned_at, 'metadata' => []]);
    $assignment = app(TourAssignmentService::class)->currentPromptableAssignmentForUser($member);
    expect($assignment)->not->toBeNull()
        ->and($assignment->tour_key)->toBe('profilpflege');
});

test('reassign resets existing assignment state', function (): void {
    $team = Team::membersTeam();
    $admin = User::factory()->create(['current_team_id' => $team->id]);
    $member = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($admin, ['role' => Role::Admin->value]);
    $team->users()->attach($member, ['role' => Role::Mitglied->value]);
    TourAssignment::create(['user_id' => $member->id, 'tour_key' => 'hauptmenue', 'tour_version' => (int) config('tours.hauptmenue.version'), 'status' => TourAssignmentStatus::Completed, 'assigned_via' => TourAssignmentSource::System, 'assigned_by_user_id' => null, 'assigned_at' => now()->subDays(3), 'started_at' => now()->subDays(3), 'completed_at' => now()->subDays(2), 'dismissed_at' => now()->subDays(2), 'next_prompt_at' => now()->subDay(), 'current_step_key' => 'profil', 'metadata' => ['completed_step_count' => 4]]);
    $assignment = app(TourAssignmentService::class)->reassign(user: $member, tourKey: 'hauptmenue', source: TourAssignmentSource::Manual, assignedBy: $admin);
    expect($assignment->status)->toBe(TourAssignmentStatus::Pending)
        ->and($assignment->assigned_via)->toBe(TourAssignmentSource::Manual)
        ->and($assignment->assigned_by_user_id)->toBe($admin->id)
        ->and($assignment->started_at)->toBeNull()
        ->and($assignment->completed_at)->toBeNull()
        ->and($assignment->dismissed_at)->toBeNull()
        ->and($assignment->next_prompt_at)->toBeNull()
        ->and($assignment->current_step_key)->toBeNull()
        ->and($assignment->metadata)->toBe([]);
});

test('ineligible profile tours are excluded before selection and upgrade', function (?Role $role, bool $outdated): void {
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
    expect($service->currentPromptableAssignmentForUser($member)?->id)->toBe($menu->id)
        ->and($service->currentPromptableAssignmentForUser($member, [$menu->id]))->toBeNull()
        ->and($profile->fresh()->status)->toBe($status)
        ->and(TourAssignment::where('user_id', $member->id)->where('tour_key', 'profilpflege')->count())->toBe(1);
    $team->users()->syncWithoutDetaching([$member->id => ['role' => Role::Mitglied->value]]);
    expect($service->currentPromptableAssignmentForUser($member)?->tour_key)->toBe('profilpflege');
})->with([[null, true], [null, false], [Role::Anwaerter, true], [Role::Anwaerter, false]]);
