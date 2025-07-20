<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;
use App\Models\Todo;
use App\Models\TodoCategory;

class MitgliederKarteControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingMember(string $role = 'Mitglied'): User
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role]);
        return $user;
    }

    private function createTodo(User $creator, array $attrs = []): Todo
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $category = TodoCategory::first() ?? TodoCategory::create(['name' => 'Test', 'slug' => 'test']);
        return Todo::create(array_merge([
            'team_id' => $team->id,
            'created_by' => $creator->id,
            'title' => 'Todo',
            'description' => 'desc',
            'points' => 5,
            'category_id' => $category->id,
            'status' => 'open',
        ], $attrs));
    }

    public function test_member_can_assign_todo(): void
    {
        $user = $this->actingMember();
        $todo = $this->createTodo($user);
        $this->actingAs($user);

        $response = $this->post(route('todos.assign', $todo));

        $response->assertRedirect(route('todos.show', $todo, false));
        $todo->refresh();
        $this->assertSame('assigned', $todo->status);
        $this->assertSame($user->id, $todo->assigned_to);
    }

    public function test_assigned_user_can_complete_todo(): void
    {
        $user = $this->actingMember();
        $todo = $this->createTodo($user, ['assigned_to' => $user->id, 'status' => 'assigned']);
        $this->actingAs($user);

        $response = $this->post(route('todos.complete', $todo));

        $response->assertRedirect(route('todos.show', $todo, false));
        $todo->refresh();
        $this->assertSame('completed', $todo->status);
        $this->assertNotNull($todo->completed_at);
    }

    public function test_admin_can_verify_completed_todo_and_award_points(): void
    {
        $assignee = $this->actingMember();
        $admin = $this->actingMember('Admin');
        $todo = $this->createTodo($admin, [
            'assigned_to' => $assignee->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        $this->actingAs($admin);

        $response = $this->post(route('todos.verify', $todo));

        $response->assertRedirect(route('todos.show', $todo, false));
        $todo->refresh();
        $this->assertSame('verified', $todo->status);
        $this->assertSame($admin->id, $todo->verified_by);
        $this->assertDatabaseHas('user_points', [
            'user_id' => $assignee->id,
            'todo_id' => $todo->id,
            'points' => $todo->points,
        ]);
    }

    public function test_assigned_user_can_release_todo(): void
    {
        $user = $this->actingMember();
        $todo = $this->createTodo($user, ['assigned_to' => $user->id, 'status' => 'assigned']);
        $this->actingAs($user);

        $response = $this->post(route('todos.release', $todo));

        $response->assertRedirect(route('todos.index', [], false));
        $todo->refresh();
        $this->assertNull($todo->assigned_to);
        $this->assertSame('open', $todo->status);
    }
}
