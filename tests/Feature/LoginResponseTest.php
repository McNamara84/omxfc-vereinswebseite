<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;
use App\Enums\Role;

class LoginResponseTest extends TestCase
{
    use RefreshDatabase;

    private function createMember(Role $role = Role::Mitglied): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role->value]);
        return $user;
    }

    public function test_anwaerter_is_logged_out_on_login(): void
    {
        $user = $this->createMember(Role::Anwaerter);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('login', absolute: false));
        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_regular_member_is_redirected_to_dashboard(): void
    {
        $user = $this->createMember();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
    }
}
