<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

covers(User::class);

test('has role and has any role', function (): void {
    $team = Team::membersTeam();
    $user = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($user, ['role' => Role::Admin->value]);
    expect($user->hasRole(Role::Admin))->toBeTrue()
        ->and($user->hasAnyRole(Role::Admin, Role::Mitglied))->toBeTrue()
        ->and($user->hasRole(Role::Mitglied))->toBeFalse()
        ->and($user->hasAnyRole(Role::Mitglied, Role::Vorstand))->toBeFalse();
});

test('profile tour eligibility depends on the members team role', function (?Role $role, bool $eligible): void {
    $membersTeam = Team::membersTeam();
    $otherTeam = Team::factory()->create(['name' => 'AG Test', 'personal_team' => false]);
    $user = User::factory()->create(['current_team_id' => $otherTeam->id]);
    if ($role !== null) {
        $membersTeam->users()->attach($user, ['role' => $role->value]);
    }
    // A different role in the selected team must neither grant nor revoke access.
    $otherRole = $eligible ? Role::Anwaerter : Role::Admin;
    $otherTeam->users()->attach($user, ['role' => $otherRole->value]);
    expect($user->role())->toBe($otherRole)
        ->and($user->canUseProfileTour())->toBe($eligible);
})->with([[null, false], [Role::Anwaerter, false], [Role::Mitwirkender, true], [Role::Mitglied, true], [Role::Ehrenmitglied, true], [Role::Kassenwart, true], [Role::Vorstand, true], [Role::Admin, true]]);
