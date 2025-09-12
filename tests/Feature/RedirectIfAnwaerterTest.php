<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;

class RedirectIfAnwaerterTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(string $role): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role]);
        return $user;
    }

    public function test_anwaerter_is_redirected_to_login(): void
    {
        $user = $this->createUser('Anwärter');
        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_regular_member_can_access_route(): void
    {
        $user = $this->createUser('Mitglied');
        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response->assertOk();
    }
}
