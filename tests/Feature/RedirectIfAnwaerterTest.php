<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;
use App\Enums\Role;

class RedirectIfAnwaerterTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(Role $role): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role->value]);
        return $user;
    }

    public function test_anwaerter_is_redirected_to_login(): void
    {
        $user = $this->createUser(Role::Anwaerter);
        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_regular_member_can_access_route(): void
    {
        $user = $this->createUser(Role::Mitglied);
        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response->assertOk();
    }
}
