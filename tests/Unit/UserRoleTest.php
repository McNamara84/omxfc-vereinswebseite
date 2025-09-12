<?php

namespace Tests\Unit;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_role_and_has_any_role(): void
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => Role::Admin->value]);

        $this->assertTrue($user->hasRole(Role::Admin));
        $this->assertTrue($user->hasAnyRole(Role::Admin, Role::Mitglied));
        $this->assertFalse($user->hasRole(Role::Mitglied));
        $this->assertFalse($user->hasAnyRole(Role::Mitglied, Role::Vorstand));
    }
}
