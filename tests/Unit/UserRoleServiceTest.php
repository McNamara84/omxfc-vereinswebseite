<?php

namespace Tests\Unit;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\UserRoleService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(UserRoleService::class)]
class UserRoleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_role_returns_members_role(): void
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => Role::Admin->value]);

        $service = new UserRoleService();

        $this->assertSame(Role::Admin, $service->getRole($user, $team));
    }

    public function test_get_role_throws_when_membership_missing(): void
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);

        $service = new UserRoleService();

        $this->expectException(ModelNotFoundException::class);

        $service->getRole($user, $team);
    }

    public function test_get_role_throws_on_invalid_role(): void
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => 'Gast']);

        $service = new UserRoleService();

        $this->expectException(ModelNotFoundException::class);

        $service->getRole($user, $team);
    }
}

