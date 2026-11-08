<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Http\Livewire\DeleteTeamForm;
use Livewire\Livewire;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('teams can be deleted', function (): void {
    $this->actingAs($user = User::factory()->withPersonalTeam()->create());
    $user->ownedTeams()->save($team = Team::factory()->make(['personal_team' => false]));
    $team->users()->attach($otherUser = User::factory()->create(), ['role' => Role::Mitglied->value]);
    Livewire::test(DeleteTeamForm::class, ['team' => $team->fresh()])->call('deleteTeam');
    expect($team->fresh())->toBeNull()
        ->and($otherUser->fresh()->teams)->toHaveCount(0);
});

test('personal teams cant be deleted', function (): void {
    $this->actingAs($user = User::factory()->withPersonalTeam()->create());
    Livewire::test(DeleteTeamForm::class, ['team' => $user->currentTeam])->call('deleteTeam')->assertHasErrors(['team']);
    expect($user->currentTeam->fresh())->not->toBeNull();
});
