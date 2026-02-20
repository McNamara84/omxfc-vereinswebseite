<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Http\Livewire\CreateTeamForm;
use Livewire\Livewire;
use Tests\TestCase;

class CreateTeamTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesUserWithRole;

    public function test_teams_can_be_created(): void
    {
        $user = $this->actingAdmin();

        Livewire::test(CreateTeamForm::class)
            ->set(['state' => ['name' => 'Test Team']])
            ->call('createTeam');

        $this->assertTrue($user->fresh()->ownedTeams()->where('name', 'Test Team')->exists());
    }

    public function test_non_admin_cannot_create_teams(): void
    {
        $user = $this->actingSimpleMember();

        Livewire::test(CreateTeamForm::class)
            ->set(['state' => ['name' => 'Mein Team']])
            ->call('createTeam')
            ->assertForbidden();
    }
}
