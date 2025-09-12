<?php

namespace Tests\Unit;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamPointService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamPointServiceTest extends TestCase
{
    use RefreshDatabase;

    private TeamPointService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TeamPointService();
    }

    private function memberWithPoints(int $points = 0): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => Role::Mitglied->value]);
        if ($points > 0) {
            $user->incrementTeamPoints($points);
        }
        return $user;
    }

    public function test_get_user_points_returns_team_points(): void
    {
        $user = $this->memberWithPoints(7);
        $this->assertSame(7, $this->service->getUserPoints($user));
    }

    public function test_assert_min_points_passes_when_enough(): void
    {
        $user = $this->memberWithPoints(10);
        $this->actingAs($user);
        $this->service->assertMinPoints(5);
        $this->assertTrue(true);
    }

    public function test_assert_min_points_throws_when_insufficient(): void
    {
        $user = $this->memberWithPoints(2);
        $this->actingAs($user);
        $this->expectException(AuthorizationException::class);
        $this->service->assertMinPoints(5);
    }
}

