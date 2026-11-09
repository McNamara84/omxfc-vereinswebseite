<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use App\Policies\TeamPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('permissions depend on team ownership', function (): void {
    $owner = User::factory()->withPersonalTeam()->create();
    $team = $owner->ownedTeams()->first();
    $member = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($member, ['role' => 'Member']);
    $outsider = User::factory()->create();
    $adminTeam = Team::membersTeam();
    $admin = User::factory()->create(['current_team_id' => $adminTeam->id]);
    $adminTeam->users()->attach($admin, ['role' => Role::Admin->value]);
    $policy = new TeamPolicy;
    expect($policy->viewAny($member))->toBeTrue()
        ->and($policy->view($member, $team))->toBeTrue()
        ->and($policy->view($outsider, $team))->toBeFalse()
        ->and($policy->update($owner, $team))->toBeTrue()
        ->and($policy->update($member, $team))->toBeFalse()
        ->and($policy->addTeamMember($owner, $team))->toBeTrue()
        ->and($policy->addTeamMember($member, $team))->toBeFalse()
        ->and($policy->addTeamMember($admin, $team))->toBeTrue()
        ->and($policy->updateTeamMember($owner, $team))->toBeTrue()
        ->and($policy->updateTeamMember($member, $team))->toBeFalse()
        ->and($policy->removeTeamMember($owner, $team))->toBeTrue()
        ->and($policy->removeTeamMember($member, $team))->toBeFalse()
        ->and($policy->delete($owner, $team))->toBeTrue()
        ->and($policy->delete($member, $team))->toBeFalse();
});
