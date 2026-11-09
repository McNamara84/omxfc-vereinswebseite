<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Http\Livewire\UpdateTeamNameForm;
use Livewire\Livewire;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('team names can be updated', function (): void {
    $this->actingAs($user = User::factory()->withPersonalTeam()->create());
    Livewire::test(UpdateTeamNameForm::class, ['team' => $user->currentTeam])->set(['state' => ['name' => 'Test Team']])->call('updateTeamName');
    expect($user->fresh()->ownedTeams)->toHaveCount(1)
        ->and($user->currentTeam->fresh()->name)->toEqual('Test Team');
});
