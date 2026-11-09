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

test('users can leave teams', function (): void {
    $user = User::factory()->withPersonalTeam()->create();
    $user->currentTeam->users()->attach($otherUser = User::factory()->create(), ['role' => Role::Admin->value]);
    $this->actingAs($otherUser);
    Livewire::test(TeamMemberManager::class, ['team' => $user->currentTeam])->call('leaveTeam');
    expect($user->currentTeam->fresh()->users)->toHaveCount(0);
});

test('team owners cant leave their own team', function (): void {
    $this->actingAs($user = User::factory()->withPersonalTeam()->create());
    Livewire::test(TeamMemberManager::class, ['team' => $user->currentTeam])->call('leaveTeam')->assertHasErrors(['team']);
    expect($user->currentTeam->fresh())->not->toBeNull();
});
