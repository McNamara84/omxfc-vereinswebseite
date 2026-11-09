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

test('team members can be removed from teams', function (): void {
    $this->actingAs($user = User::factory()->withPersonalTeam()->create());
    $user->currentTeam->users()->attach($otherUser = User::factory()->create(), ['role' => Role::Admin->value]);
    Livewire::test(TeamMemberManager::class, ['team' => $user->currentTeam])->set('teamMemberIdBeingRemoved', $otherUser->id)->call('removeTeamMember');
    expect($user->currentTeam->fresh()->users)->toHaveCount(0);
});

test('only team owner can remove team members', function (): void {
    $user = User::factory()->withPersonalTeam()->create();
    $user->currentTeam->users()->attach($otherUser = User::factory()->create(), ['role' => Role::Admin->value]);
    $this->actingAs($otherUser);
    Livewire::test(TeamMemberManager::class, ['team' => $user->currentTeam])->set('teamMemberIdBeingRemoved', $user->id)->call('removeTeamMember')->assertStatus(403);
});
