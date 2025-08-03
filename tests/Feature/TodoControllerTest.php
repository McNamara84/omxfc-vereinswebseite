<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;
use App\Models\Todo;
use App\Models\TodoCategory;

class TodoControllerTest extends TestCase
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

    public function test_member_cannot_verify_other_users_todo(): void
    {
        $assignee = $this->actingMember();
        $admin = $this->actingMember('Admin');
        $todo = $this->createTodo($admin, [
            'assigned_to' => $assignee->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        $member = $this->actingMember();
        $this->actingAs($member);

        $this->post(route('todos.verify', $todo))->assertForbidden();

        $todo->refresh();
        $this->assertSame('completed', $todo->status);
        $this->assertNull($todo->verified_by);
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

    public function test_creator_can_update_todo(): void
    {
        $user = $this->actingMember('Admin');
        $todo = $this->createTodo($user);
        $category = TodoCategory::first();
        $this->actingAs($user);

        $response = $this->put(route('todos.update', $todo), [
            'title' => 'Updated',
            'description' => 'New desc',
            'points' => 10,
            'category_id' => $category->id,
        ]);

        $response->assertRedirect(route('todos.show', $todo, false));
        $todo->refresh();
        $this->assertSame('Updated', $todo->title);
        $this->assertSame(10, $todo->points);
    }

    public function test_non_creator_cannot_update_todo(): void
    {
        $creator = $this->actingMember('Admin');
        $other = $this->actingMember();
        $todo = $this->createTodo($creator);
        $this->actingAs($other);

        $this->put(route('todos.update', $todo), [
            'title' => 'X',
            'description' => 'Y',
            'points' => 5,
            'category_id' => TodoCategory::first()->id,
        ])->assertForbidden();

        $todo->refresh();
        $this->assertSame('Todo', $todo->title);
        $this->assertSame('desc', $todo->description);
        $this->assertSame(5, $todo->points);
    }

    public function test_index_displays_lists_and_points(): void
    {
        $user = $this->actingMember();
        $other = $this->actingMember();
        $todoOpen = $this->createTodo($user);
        $todoAssigned = $this->createTodo($user, ['assigned_to' => $user->id, 'status' => 'assigned']);
        $todoCompleted = $this->createTodo($user, ['assigned_to' => $other->id, 'status' => 'completed']);

        \App\Models\UserPoint::create([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'points' => 3,
        ]);

        $this->actingAs($user);
        $response = $this->get('/aufgaben');

        $response->assertOk();
        $response->assertViewIs('todos.index');
        $response->assertViewHas('assignedTodos', function ($c) use ($todoAssigned) {
            return $c->contains($todoAssigned);
        });
        $response->assertViewHas('unassignedTodos', function ($c) use ($todoOpen) {
            return $c->contains($todoOpen);
        });
        $response->assertViewHas('completedTodos', function ($c) use ($todoCompleted) {
            return $c->contains($todoCompleted);
        });
        $response->assertViewHas('userPoints', 3);
    }

    public function test_store_creates_todo_for_admin(): void
    {
        $user = $this->actingMember('Admin');
        $category = TodoCategory::first() ?? TodoCategory::create(['name' => 'Test', 'slug' => 'test']);
        $this->actingAs($user);

        $response = $this->post('/aufgaben', [
            'title' => 'New Todo',
            'description' => 'Desc',
            'points' => 7,
            'category_id' => $category->id,
        ]);

        $response->assertRedirect(route('todos.index', [], false));
        $this->assertDatabaseHas('todos', [
            'title' => 'New Todo',
            'created_by' => $user->id,
        ]);
    }

    public function test_show_displays_todo_and_permissions(): void
    {
        $user = $this->actingMember();
        $todo = $this->createTodo($user);
        $this->actingAs($user);

        $response = $this->get(route('todos.show', $todo));

        $response->assertOk();
        $response->assertViewIs('todos.show');
        $response->assertViewHas('canAssign', true);
        $response->assertViewHas('canEdit', true);
    }

    public function test_edit_page_loads_for_creator(): void
    {
        $user = $this->actingMember('Admin');
        $todo = $this->createTodo($user);
        $this->actingAs($user);

        $response = $this->get(route('todos.edit', $todo));

        $response->assertOk();
        $response->assertViewIs('todos.edit');
    }
}
