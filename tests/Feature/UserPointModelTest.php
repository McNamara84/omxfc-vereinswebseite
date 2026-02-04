<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\Todo;
use App\Models\TodoCategory;
use App\Models\User;
use App\Models\UserPoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPointModelTest extends TestCase
{
    use RefreshDatabase;

    private function createMember(): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => \App\Enums\Role::Mitglied->value]);

        return $user;
    }

    private function createTodo(User $creator): Todo
    {
        $team = Team::membersTeam();
        $category = TodoCategory::first() ?? TodoCategory::create(['name' => 'Test', 'slug' => 'test']);

        return Todo::create([
            'team_id' => $team->id,
            'created_by' => $creator->id,
            'title' => 'Todo',
            'points' => 5,
            'category_id' => $category->id,
        ]);
    }

    public function test_user_point_can_be_created(): void
    {
        $user = $this->createMember();
        $todo = $this->createTodo($user);

        $userPoint = UserPoint::create([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'todo_id' => $todo->id,
            'points' => 10,
        ]);

        $this->assertDatabaseHas('user_points', [
            'id' => $userPoint->id,
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'todo_id' => $todo->id,
            'points' => 10,
        ]);
    }

    public function test_user_point_relations_return_models(): void
    {
        $user = $this->createMember();
        $todo = $this->createTodo($user);

        $userPoint = UserPoint::create([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'todo_id' => $todo->id,
            'points' => 1,
        ]);

        $this->assertTrue($userPoint->user->is($user));
        $this->assertTrue($userPoint->team->is($user->currentTeam));
        $this->assertTrue($userPoint->todo->is($todo));
    }

    public function test_total_points_for_team_returns_sum(): void
    {
        $user = $this->createMember();
        $todo = $this->createTodo($user);

        UserPoint::create([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'todo_id' => $todo->id,
            'points' => 2,
        ]);
        UserPoint::create([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'todo_id' => $todo->id,
            'points' => 3,
        ]);

        $points = $user->totalPointsForTeam($user->currentTeam);

        $this->assertSame(5, $points);
    }

    public function test_increment_team_points_creates_entry(): void
    {
        $user = $this->createMember();

        $user->incrementTeamPoints(4);

        $this->assertDatabaseHas('user_points', [
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'points' => 4,
        ]);
    }
}
