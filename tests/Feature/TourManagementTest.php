<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TourAssignmentSource;
use App\Enums\TourAssignmentStatus;
use App\Models\Team;
use App\Models\TourAssignment;
use App\Services\TourAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CreatesUserWithRole::class, RefreshDatabase::class);

test('profile page shows tour selfservice panel', function (): void {
    $member = $this->createUserWithRole(Role::Mitglied);
    TourAssignment::query()->create(['user_id' => $member->id, 'tour_key' => 'hauptmenue', 'tour_version' => (int) config('tours.hauptmenue.version'), 'status' => TourAssignmentStatus::Completed, 'assigned_via' => TourAssignmentSource::System, 'assigned_at' => now()->subDay(), 'completed_at' => now()->subHours(12), 'metadata' => []]);
    $this->actingAs($member)->get(route('profile.show'))->assertOk()->assertSeeText('Touren & Hilfestart')->assertSeeText('Hauptmenü entdecken')->assertSeeText('Profil pflegen')->assertSee(route('touren.restart', 'hauptmenue'));
});

test('profile page renders without tour assignments table', function (): void {
    $member = $this->createUserWithRole(Role::Mitglied);
    Schema::drop('tour_assignments');
    $this->actingAs($member)->get(route('profile.show'))->assertOk()->assertDontSeeText('Touren & Hilfestart');
});

test('completion note requires an eligible members team role', function (?Role $role): void {
    $member = $this->createUserWithRole($role ?? Role::Mitglied);
    if ($role === null) {
        Team::membersTeam()->users()->detach($member);
    }
    $otherTeam = Team::factory()->create(['name' => 'AG Test', 'personal_team' => false]);
    $otherTeam->users()->attach($member, ['role' => Role::Admin->value]);
    $member->forceFill(['current_team_id' => $otherTeam->id])->save();
    $response = $this->actingAs($member->refresh())->get(route('profile.show'))->assertOk()->assertSeeText('Touren & Hilfestart')->assertSee('data-profile-series-form', escape: false);
    if ($role !== null && $role !== Role::Anwaerter) {
        $response->assertSee('data-tour-profile-key="profile-complete"', escape: false);
    } else {
        $response->assertDontSee('data-tour-profile-key="profile-complete"', escape: false);
    }
})->with([[null], [Role::Anwaerter], [Role::Mitwirkender], [Role::Mitglied], [Role::Ehrenmitglied], [Role::Kassenwart], [Role::Vorstand], [Role::Admin]]);

test('profile reward copy uses overview amount and field count', function (bool $rewardReceived): void {
    $member = $this->createUserWithRole(Role::Mitglied);
    $overview = app(TourAssignmentService::class)->selfServiceOverviewForUser($member)->firstWhere('definition.key', 'profilpflege');
    $overview['reward_points'] = 9;
    $overview['profile_field_count'] = 13;
    $overview['reward_received'] = $rewardReceived;
    $this->mock(TourAssignmentService::class)->shouldReceive('selfServiceOverviewForUser')->once()->withArgs(fn ($user) => $user->is($member))->andReturn(collect([$overview]));
    $this->actingAs($member)->get(route('profile.show'))->assertOk()->assertSeeText($rewardReceived ? 'Die einmaligen 9 Baxx hast du bereits erhalten.' : 'Einmalig 9 Baxx: Alle 13 Serienangaben speichern und die Tour abschließen.');
})->with([[false], [true]]);

test('profile tour actions require an eligible members team role', function (?Role $role, ?TourAssignmentStatus $status, bool $eligible): void {
    $member = $this->createUserWithRole($role ?? Role::Mitglied);
    if ($role === null) {
        Team::membersTeam()->users()->detach($member);
    }
    $otherTeam = Team::factory()->create(['name' => 'AG Test', 'personal_team' => false]);
    $otherTeam->users()->attach($member, ['role' => ($eligible ? Role::Anwaerter : Role::Admin)->value]);
    $member->forceFill(['current_team_id' => $otherTeam->id])->save();
    $assignment = null;
    if ($status !== null) {
        $assignment = app(TourAssignmentService::class)->assignIfMissing($member, 'profilpflege', TourAssignmentSource::System);
        $assignment->forceFill(['status' => $status])->save();
    }
    $response = $this->actingAs($member->refresh())->get(route('profile.show'))->assertOk()->assertSeeText('Profil pflegen')->assertSee('action="'.route('touren.restart', 'hauptmenue').'"', escape: false);
    $restartAction = 'action="'.route('touren.restart', 'profilpflege').'"';
    if ($eligible) {
        $response->assertSee($restartAction, escape: false);
    } else {
        $response->assertDontSee($restartAction, escape: false);
    }
    if ($eligible && in_array($status, [TourAssignmentStatus::Pending, TourAssignmentStatus::InProgress], true)) {
        $response->assertSee('action="'.route('touren.resume', $assignment).'"', escape: false);
    } else {
        $response->assertDontSeeText('Tour fortsetzen');
    }
})->with([[null, null, false], [null, TourAssignmentStatus::Pending, false], [null, TourAssignmentStatus::InProgress, false], [null, TourAssignmentStatus::Completed, false], [Role::Anwaerter, null, false], [Role::Anwaerter, TourAssignmentStatus::Pending, false], [Role::Anwaerter, TourAssignmentStatus::InProgress, false], [Role::Anwaerter, TourAssignmentStatus::Completed, false], [Role::Mitglied, null, true], [Role::Mitglied, TourAssignmentStatus::Pending, true], [Role::Mitglied, TourAssignmentStatus::InProgress, true], [Role::Mitglied, TourAssignmentStatus::Completed, true]]);

test('vorstand can view tour admin page', function (): void {
    $vorstand = $this->createUserWithRole(Role::Vorstand);
    $member = $this->createUserWithRole(Role::Mitglied);
    $this->actingAs($vorstand)->get(route('admin.touren.index'))->assertOk()->assertSeeText('Touren verwalten')->assertSeeText($member->name)->assertSeeText('Hauptmenü entdecken')->assertSeeText('Profil pflegen');
});

test('vorstand can reassign tour for member', function (): void {
    $vorstand = $this->createUserWithRole(Role::Vorstand);
    $member = $this->createUserWithRole(Role::Mitglied);
    $assignment = TourAssignment::query()->create(['user_id' => $member->id, 'tour_key' => 'hauptmenue', 'tour_version' => (int) config('tours.hauptmenue.version'), 'status' => TourAssignmentStatus::Completed, 'assigned_via' => TourAssignmentSource::System, 'assigned_at' => now()->subDay(), 'started_at' => now()->subDay(), 'completed_at' => now()->subHours(12), 'current_step_key' => 'profile-settings', 'metadata' => []]);
    $this->actingAs($vorstand)->post(route('admin.touren.assign'), ['user_id' => $member->id, 'tour_key' => 'hauptmenue'])->assertRedirect(route('admin.touren.index'));
    $this->assertDatabaseHas('tour_assignments', ['id' => $assignment->id, 'status' => TourAssignmentStatus::Pending->value, 'assigned_via' => TourAssignmentSource::Manual->value, 'assigned_by_user_id' => $vorstand->id, 'current_step_key' => null]);
});

test('vorstand can assign profile tour for member', function (): void {
    $vorstand = $this->createUserWithRole(Role::Vorstand);
    $member = $this->createUserWithRole(Role::Mitglied);
    $this->actingAs($vorstand)->post(route('admin.touren.assign'), ['user_id' => $member->id, 'tour_key' => 'profilpflege'])->assertRedirect(route('admin.touren.index'));
    $this->assertDatabaseHas('tour_assignments', ['user_id' => $member->id, 'tour_key' => 'profilpflege', 'tour_version' => (int) config('tours.profilpflege.version'), 'status' => TourAssignmentStatus::Pending->value, 'assigned_via' => TourAssignmentSource::Manual->value, 'assigned_by_user_id' => $vorstand->id]);
});

test('regular member cannot access tour admin page', function (): void {
    $member = $this->createUserWithRole(Role::Mitglied);
    $this->actingAs($member)->get(route('admin.touren.index'))->assertForbidden();
});
