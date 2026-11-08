<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Http\Livewire\TeamMemberManager;
use Livewire\Livewire;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('team member roles can be updated', function (): void {
    $this->actingAs($user = User::factory()->withPersonalTeam()->create());
    $user->currentTeam->users()->attach($otherUser = User::factory()->create(), ['role' => Role::Admin->value]);
    Livewire::test(TeamMemberManager::class, ['team' => $user->currentTeam])->set('managingRoleFor', $otherUser)->set('currentRole', 'Mitglied')->call('updateRole');
    expect($otherUser->fresh()->hasTeamRole($user->currentTeam->fresh(), 'Mitglied'))->toBeTrue();
});

test('only team owner can update team member roles', function (): void {
    $user = User::factory()->withPersonalTeam()->create();
    $user->currentTeam->users()->attach($otherUser = User::factory()->create(), ['role' => Role::Admin->value]);
    $this->actingAs($otherUser);
    Livewire::test(TeamMemberManager::class, ['team' => $user->currentTeam])->set('managingRoleFor', $otherUser)->set('currentRole', 'Mitglied')->call('updateRole')->assertStatus(403);
    expect($otherUser->fresh()->hasTeamRole($user->currentTeam->fresh(), 'Admin'))->toBeTrue();
});
