<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TourAssignmentSource;
use App\Enums\TourAssignmentStatus;
use App\Models\Team;
use App\Models\TourAssignment;
use App\Services\TourAssignmentService;
use App\Services\TourRegistry;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(TourControllerTestFixtures::class);

test('current returns open tour payload for authenticated member', function (): void {
    $member = $this->createMember();
    $assignment = $this->createPendingAssignment($member);
    $this->actingAs($member)->getJson(route('touren.current'))->assertOk()->assertJsonPath('tour.assignment_id', $assignment->id)->assertJsonPath('tour.key', 'hauptmenue')->assertJsonPath('tour.status', TourAssignmentStatus::Pending->value)->assertJsonCount(count(app(TourRegistry::class)->definition('hauptmenue')->steps), 'tour.steps');
});

test('former members do not receive profile invitations and cannot mutate them', function (): void {
    $member = $this->createMember();
    $profile = app(TourAssignmentService::class)->assignIfMissing($member, 'profilpflege', TourAssignmentSource::System);
    $menu = $this->createPendingAssignment($member);
    $this->actingAs($member)->getJson(route('touren.current'))->assertOk()->assertJsonPath('tour.assignment_id', $profile->id);
    Team::membersTeam()->users()->detach($member);
    $this->getJson(route('touren.current'))->assertOk()->assertJsonPath('tour.assignment_id', $menu->id);
    foreach (['start', 'dismiss', 'resume', 'progress', 'complete'] as $action) {
        $this->postJson(route('touren.'.$action, $profile), ['step_key' => 'profile-complete'])->assertForbidden();
    }
    expect($profile->fresh()->status)->toBe(TourAssignmentStatus::Pending)
        ->and($profile->fresh()->dismissed_at)->toBeNull();
});

test('current conditionally includes cover ratings navigation step', function (): void {
    config(['cover-ratings.enabled' => true]);
    $member = $this->createMember();
    $assignment = $this->createPendingAssignment($member);
    $response = $this->actingAs($member)->getJson(route('touren.current'))->assertOk()->assertJsonPath('tour.assignment_id', $assignment->id)->assertJsonCount(37, 'tour.steps');
    $coverStep = collect($response->json('tour.steps'))->firstWhere('key', 'community-cover-ratings');
    expect($coverStep['title'] ?? null)->toBe('Cover-Bewertungen')
        ->and($coverStep ?? [])->not->toHaveKey('config_enabled');
});

test('current upgrades outdated assignment before returning payload', function (): void {
    $member = $this->createMember();
    $staleAssignment = $this->createPendingAssignment($member, 1);
    $currentVersion = (int) config('tours.hauptmenue.version');
    $response = $this->actingAs($member)->getJson(route('touren.current'))->assertOk()->assertJsonPath('tour.key', 'hauptmenue')->assertJsonPath('tour.version', $currentVersion)->assertJsonPath('tour.status', TourAssignmentStatus::Pending->value);
    $assignmentId = $response->json('tour.assignment_id');
    expect($assignmentId)->toBeInt()->not->toBe($staleAssignment->id);
    $this->assertDatabaseHas('tour_assignments', ['id' => $assignmentId, 'user_id' => $member->id, 'tour_key' => 'hauptmenue', 'tour_version' => $currentVersion, 'status' => TourAssignmentStatus::Pending->value]);
    expect($staleAssignment->fresh()->status)->toBe(TourAssignmentStatus::Completed);
});

test('bildersuche step is available only when enabled after upgrading the previous tour', function (bool $enabled): void {
    config(['bildersuche.enabled' => $enabled, 'cover-ratings.enabled' => true]);
    $member = $this->createMember();
    $previous = $this->createPendingAssignment($member, 7);
    $response = $this->actingAs($member)->getJson(route('touren.current'))->assertOk()->assertJsonPath('tour.version', 8)->assertJsonCount($enabled ? 38 : 37, 'tour.steps');
    expect($previous->fresh()->metadata['superseded_by_version'])->toBe(8);
    $steps = collect($response->json('tour.steps'));
    expect($steps->contains('key', 'content-bildersuche'))->toBe($enabled);
    $progress = $this->postJson(route('touren.progress', $response->json('tour.assignment_id')), ['step_key' => 'content-bildersuche']);
    if ($enabled) {
        expect($steps->firstWhere('key', 'content-bildersuche'))->not->toHaveKey('config_enabled');
        $progress->assertOk()->assertJsonPath('tour.current_step_key', 'content-bildersuche');
    } else {
        $progress->assertUnprocessable()->assertJsonValidationErrors(['step_key']);
    }
})->with([[true], [false]]);

test('progress starts tour and updates current step', function (): void {
    $member = $this->createMember();
    $assignment = $this->createPendingAssignment($member);
    $this->actingAs($member)->postJson(route('touren.progress', $assignment), ['step_key' => 'profile-settings'])->assertOk()->assertJsonPath('tour.current_step_key', 'profile-settings')->assertJsonPath('tour.status', TourAssignmentStatus::InProgress->value)->assertJsonPath('tour.current_step_index', collect(app(TourRegistry::class)->definition('hauptmenue')->steps)->search(fn ($step) => $step['key'] === 'profile-settings'));
    $this->assertDatabaseHas('tour_assignments', ['id' => $assignment->id, 'status' => TourAssignmentStatus::InProgress->value, 'current_step_key' => 'profile-settings']);
    expect($assignment->fresh()->started_at)->not->toBeNull();
});

test('previous navigation tour upgrades and accepts character step progress', function (): void {
    $member = $this->createMember();
    $rpg = Team::factory()->create(['name' => 'AG Rollenspiel', 'personal_team' => false]);
    $rpg->users()->attach($member, ['role' => Role::Mitglied->value]);
    $previous = $this->createPendingAssignment($member, 5);
    $response = $this->actingAs($member)->getJson(route('touren.current'))->assertOk()->assertJsonPath('tour.version', 8);
    $steps = collect($response->json('tour.steps'));
    expect($steps->where('key', 'teams-characters')->count())->toBe(1)
        ->and($previous->fresh()->metadata['superseded_by_version'])->toBe(8)
        ->and($response->json('tour.assignment_id'))->not->toBe($previous->id);
    $this->postJson(route('touren.progress', $response->json('tour.assignment_id')), ['step_key' => 'teams-characters'])->assertOk()->assertJsonPath('tour.current_step_key', 'teams-characters')->assertJsonPath('tour.current_step_index', $steps->search(fn ($step) => $step['key'] === 'teams-characters'));
});

test('progress rejects step key outside tour definition', function (): void {
    $member = $this->createMember();
    $assignment = $this->createPendingAssignment($member);
    $this->actingAs($member)->postJson(route('touren.progress', $assignment), ['step_key' => 'ungueltiger-schritt'])->assertStatus(422)->assertJsonValidationErrors(['step_key']);
    $this->assertDatabaseHas('tour_assignments', ['id' => $assignment->id, 'status' => TourAssignmentStatus::Pending->value, 'current_step_key' => null]);
});

test('dismiss suppresses tour for current session', function (): void {
    $member = $this->createMember();
    $assignment = $this->createPendingAssignment($member);
    $this->actingAs($member)->postJson(route('touren.dismiss', $assignment))->assertOk()->assertJsonPath('suppressed', true);
    $this->actingAs($member)->getJson(route('touren.current'))->assertOk()->assertJsonPath('tour', null);
    expect($assignment->fresh()->dismissed_at)->not->toBeNull();
});

test('complete marks tour completed and hides it from current payload', function (): void {
    $member = $this->createMember();
    $assignment = $this->createPendingAssignment($member);
    $this->actingAs($member)->postJson(route('touren.complete', $assignment))->assertOk()->assertJsonPath('completed', true)->assertJsonPath('reward', null);
    $this->assertDatabaseHas('tour_assignments', ['id' => $assignment->id, 'status' => TourAssignmentStatus::Completed->value]);
    $this->actingAs($member)->getJson(route('touren.current'))->assertOk()->assertJsonPath('tour', null);
});

test('member can restart own tour from selfservice', function (): void {
    $member = $this->createMember();
    $assignment = $this->createPendingAssignment($member);
    $assignment->forceFill(['status' => TourAssignmentStatus::Completed, 'started_at' => now()->subMinutes(5), 'completed_at' => now()->subMinutes(2), 'current_step_key' => 'profile-settings'])->save();
    $this->actingAs($member)->from(route('profile.show'))->post(route('touren.restart', 'hauptmenue'))->assertRedirect(route('profile.show'));
    $this->assertDatabaseHas('tour_assignments', ['id' => $assignment->id, 'status' => TourAssignmentStatus::Pending->value, 'assigned_via' => TourAssignmentSource::SelfService->value, 'current_step_key' => null]);
});

test('resume rejects other tours without mutating assignments or session', function (?int $version): void {
    $member = $this->createMember();
    $menu = $this->createPendingAssignment($member, $version);
    $profile = app(TourAssignmentService::class)->assignIfMissing($member, 'profilpflege', TourAssignmentSource::System);
    $before = $menu->fresh()->getAttributes();
    $this->actingAs($member)->withSession([TourAssignmentService::SESSION_ACTIVE_KEY => $profile->id, TourAssignmentService::SESSION_SUPPRESSION_KEY => [$menu->id]])->post(route('touren.resume', $menu))->assertNotFound()->assertSessionHas(TourAssignmentService::SESSION_ACTIVE_KEY, $profile->id)->assertSessionHas(TourAssignmentService::SESSION_SUPPRESSION_KEY, [$menu->id]);
    expect($menu->fresh()->getAttributes())->toBe($before)
        ->and(TourAssignment::where('user_id', $member->id)->where('tour_key', 'hauptmenue')->count())->toBe(1);
})->with([[null], [1]]);

test('resume rejects another members profile tour', function (): void {
    $owner = $this->createMember();
    $profile = app(TourAssignmentService::class)->assignIfMissing($owner, 'profilpflege', TourAssignmentSource::System);
    $this->actingAs($this->createMember())->post(route('touren.resume', $profile))->assertForbidden();
    expect($profile->fresh()->started_at)->toBeNull();
});

test('ineligible users cannot create or reset profile tours', function (?Role $role, bool $existing): void {
    $member = $this->createMember();
    $profile = $existing ? app(TourAssignmentService::class)->assignIfMissing($member, 'profilpflege', TourAssignmentSource::System) : null;
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
    $this->actingAs($member->refresh())->withSession([TourAssignmentService::SESSION_ACTIVE_KEY => 999, TourAssignmentService::SESSION_SUPPRESSION_KEY => $suppressed])->post(route('touren.restart', 'profilpflege'))->assertForbidden()->assertSessionHas(TourAssignmentService::SESSION_ACTIVE_KEY, 999)->assertSessionHas(TourAssignmentService::SESSION_SUPPRESSION_KEY, $suppressed);
    expect(TourAssignment::where('user_id', $member->id)->where('tour_key', 'profilpflege')->count())->toBe($existing ? 1 : 0)
        ->and($profile?->fresh()->getAttributes())->toBe($before);
    $this->from(route('profile.show'))->post(route('touren.restart', 'hauptmenue'))->assertRedirect(route('profile.show'));
})->with([[null, false], [null, true], [Role::Anwaerter, false], [Role::Anwaerter, true]]);

test('eligible member roles can restart the profile tour', function (Role $role): void {
    $member = $this->createMember();
    Team::membersTeam()->users()->updateExistingPivot($member->id, ['role' => $role->value]);
    $this->actingAs($member->refresh())->from(route('profile.show'))->post(route('touren.restart', 'profilpflege'))->assertRedirect(route('profile.show'));
    $this->assertDatabaseHas('tour_assignments', ['user_id' => $member->id, 'tour_key' => 'profilpflege', 'status' => TourAssignmentStatus::Pending->value, 'assigned_via' => TourAssignmentSource::SelfService->value]);
})->with([[Role::Mitwirkender], [Role::Mitglied], [Role::Ehrenmitglied], [Role::Kassenwart], [Role::Vorstand], [Role::Admin]]);

test('restart rejects unknown tour key', function (): void {
    $member = $this->createMember();
    $this->actingAs($member)->postJson(route('touren.restart', 'unbekannt'))->assertStatus(422)->assertJsonValidationErrors(['tour_key']);
});

test('restart rejects non self service tour key', function (): void {
    $member = $this->createMember();
    Config::set('tours.profilpflege.self_service_enabled', false);
    $this->actingAs($member)->postJson(route('touren.restart', 'profilpflege'))->assertStatus(422)->assertJsonValidationErrors(['tour_key']);
    $this->assertDatabaseMissing('tour_assignments', ['user_id' => $member->id, 'tour_key' => 'profilpflege', 'assigned_via' => TourAssignmentSource::SelfService->value]);
});

test('member cannot control foreign assignment', function (): void {
    $owner = $this->createMember();
    $intruder = $this->createMember();
    $assignment = $this->createPendingAssignment($owner);
    $this->actingAs($intruder)->postJson(route('touren.dismiss', $assignment))->assertForbidden();
});

test('current ignores session suppressed assignment until logout', function (): void {
    $member = $this->createMember();
    $assignment = $this->createPendingAssignment($member);
    $this->actingAs($member)->withSession([TourAssignmentService::SESSION_SUPPRESSION_KEY => [$assignment->id]])->getJson(route('touren.current'))->assertOk()->assertJsonPath('tour', null);
});
