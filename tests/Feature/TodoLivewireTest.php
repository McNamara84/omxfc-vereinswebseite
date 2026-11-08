<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TodoStatus;
use App\Livewire\TodoForm;
use App\Livewire\TodoIndex;
use App\Livewire\TodoShow;
use App\Models\Team;
use App\Models\Todo;
use App\Models\TodoCategory;
use App\Models\UserPoint;
use App\Services\MembersTeamProvider;
use Livewire\Livewire;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(TodoLivewireTestFixtures::class);

test('index displays user points', function (): void {
    $user = $this->actingMember();
    $user->incrementTeamPoints(3);
    Livewire::actingAs($user)->test(TodoIndex::class)->assertSeeText('3')->assertOk();
});

test('index uses members team points when current team differs', function (): void {
    $user = $this->actingMember();
    $user->incrementTeamPoints(37);
    $otherTeam = Team::factory()->create(['personal_team' => false, 'name' => 'Nebenverein']);
    $otherTeam->users()->attach($user, ['role' => Role::Mitglied->value]);
    $user->forceFill(['current_team_id' => $otherTeam->id])->save();
    Livewire::actingAs($user->fresh())->test(TodoIndex::class)->assertSet('userPoints', 37)->assertSeeText('37')->assertOk();
});

test('dashboard copy uses club language', function (): void {
    $user = $this->actingMember();
    Livewire::actingAs($user)->test(TodoIndex::class)->assertSeeText('Vereins-Dashboard')->assertSeeText('Vereinsdurchschnitt')->assertSeeText('So steht dein Verein aktuell da.')->assertSeeText('Dein Punktestand')->assertDontSeeText('Teamdurchschnitt')->assertDontSeeText('Deine Baxx');
});

test('dashboard formats large numbers and hides trend card', function (): void {
    $user = $this->actingMember();
    $user->incrementTeamPoints(12345);
    Livewire::actingAs($user)->test(TodoIndex::class)->assertSeeText('12.345')->assertDontSeeText('Aktivität der letzten 7 Tage');
});

test('dashboard displays leaderboard without goal card', function (): void {
    $user = $this->actingMember();
    Livewire::actingAs($user)->test(TodoIndex::class)->assertDontSeeText('Dein nächstes Ziel')->assertSeeTextInOrder(['Dein Punktestand', 'Rangliste'])->assertSeeHtml('data-todo-dashboard')->assertSeeHtml('xl:grid-cols-4');
});

test('index displays sections in new order for admin', function (): void {
    $assignee = $this->actingMember();
    $admin = $this->actingMember('Admin');
    $this->createTodo($admin, ['assigned_to' => $assignee->id, 'status' => TodoStatus::Completed->value, 'completed_at' => now()]);
    $this->createTodo($admin, ['assigned_to' => $assignee->id, 'status' => TodoStatus::Assigned->value]);
    $this->createTodo($admin, ['assigned_to' => $admin->id, 'status' => TodoStatus::Assigned->value]);
    $this->createTodo($admin, ['status' => TodoStatus::Open->value]);
    Livewire::actingAs($admin)->test(TodoIndex::class)->assertSeeTextInOrder(['Zu verifizierende Challenges', 'In Bearbeitung befindliche Challenges', 'Deine Challenges', 'Offene Challenges', 'Vereins-Dashboard']);
});

test('index displays lists and points', function (): void {
    $user = $this->actingMember();
    $other = $this->actingMember();
    $todoOpen = $this->createTodo($user);
    $todoAssigned = $this->createTodo($user, ['assigned_to' => $user->id, 'status' => TodoStatus::Assigned->value]);
    $todoCompleted = $this->createTodo($user, ['assigned_to' => $other->id, 'status' => TodoStatus::Completed->value]);
    UserPoint::create(['user_id' => $user->id, 'team_id' => $user->currentTeam->id, 'points' => 3]);
    Livewire::actingAs($user)->test(TodoIndex::class)->assertSeeText($todoOpen->title)->assertSeeText($todoAssigned->title)->assertOk();
});

test('member can assign todo', function (): void {
    $user = $this->actingMember();
    $todo = $this->createTodo($user);
    Livewire::actingAs($user)->test(TodoIndex::class)->call('assign', $todo->id)->assertHasNoErrors();
    $todo->refresh();
    expect($todo->status)->toBe(TodoStatus::Assigned)
        ->and($todo->assigned_to)->toBe($user->id);
    $this->assertDatabaseHas('activities', ['user_id' => $user->id, 'subject_id' => $todo->id, 'action' => 'accepted']);
});

test('assigned user can complete todo', function (): void {
    $user = $this->actingMember();
    $todo = $this->createTodo($user, ['assigned_to' => $user->id, 'status' => TodoStatus::Assigned->value]);
    Livewire::actingAs($user)->test(TodoIndex::class)->call('complete', $todo->id)->assertHasNoErrors();
    $todo->refresh();
    expect($todo->status)->toBe(TodoStatus::Completed)
        ->and($todo->completed_at)->not->toBeNull();
});

test('admin can verify completed todo and award points', function (): void {
    $assignee = $this->actingMember();
    $admin = $this->actingMember('Admin');
    $todo = $this->createTodo($admin, ['assigned_to' => $assignee->id, 'status' => TodoStatus::Completed->value, 'completed_at' => now()]);
    Livewire::actingAs($admin)->test(TodoIndex::class)->call('verify', $todo->id)->assertHasNoErrors();
    $todo->refresh();
    expect($todo->status)->toBe(TodoStatus::Verified)
        ->and($todo->verified_by)->toBe($admin->id);
    $this->assertDatabaseHas('user_points', ['user_id' => $assignee->id, 'todo_id' => $todo->id, 'points' => $todo->points]);
    $this->assertDatabaseHas('activities', ['user_id' => $assignee->id, 'subject_id' => $todo->id, 'action' => 'completed']);
});

test('member cannot verify other users todo', function (): void {
    $assignee = $this->actingMember();
    $admin = $this->actingMember('Admin');
    $todo = $this->createTodo($admin, ['assigned_to' => $assignee->id, 'status' => TodoStatus::Completed->value, 'completed_at' => now()]);
    $member = $this->actingMember();
    Livewire::actingAs($member)->test(TodoIndex::class)->call('verify', $todo->id)->assertForbidden();
    $todo->refresh();
    expect($todo->status)->toBe(TodoStatus::Completed)
        ->and($todo->verified_by)->toBeNull();
});

test('assigned user can release todo', function (): void {
    $user = $this->actingMember();
    $todo = $this->createTodo($user, ['assigned_to' => $user->id, 'status' => TodoStatus::Assigned->value]);
    Livewire::actingAs($user)->test(TodoIndex::class)->call('release', $todo->id)->assertHasNoErrors();
    $todo->refresh();
    expect($todo->assigned_to)->toBeNull()
        ->and($todo->status)->toBe(TodoStatus::Open);
});

test('assigning already taken todo returns error', function (): void {
    $member = $this->actingMember();
    $other = $this->actingMember();
    $todo = $this->createTodo($member, ['assigned_to' => $other->id, 'status' => TodoStatus::Assigned->value]);
    Livewire::actingAs($member)->test(TodoIndex::class)->call('assign', $todo->id)->assertDispatched('toast');
});

test('user cannot complete unassigned todo', function (): void {
    $member = $this->actingMember();
    $other = $this->actingMember();
    $todo = $this->createTodo($other, ['assigned_to' => $other->id, 'status' => TodoStatus::Assigned->value]);
    Livewire::actingAs($member)->test(TodoIndex::class)->call('complete', $todo->id)->assertDispatched('toast');
    $todo->refresh();
    expect($todo->status)->toBe(TodoStatus::Assigned);
});

test('user cannot release unassigned todo', function (): void {
    $member = $this->actingMember();
    $other = $this->actingMember();
    $todo = $this->createTodo($other, ['assigned_to' => $other->id, 'status' => TodoStatus::Assigned->value]);
    Livewire::actingAs($member)->test(TodoIndex::class)->call('release', $todo->id)->assertDispatched('toast');
    $todo->refresh();
    expect($todo->assigned_to)->toBe($other->id);
});

test('verify rejects tasks that are not completed', function (): void {
    $admin = $this->actingMember('Admin');
    $todo = $this->createTodo($admin);
    Livewire::actingAs($admin)->test(TodoIndex::class)->call('verify', $todo->id)->assertDispatched('toast');
    $todo->refresh();
    expect($todo->status)->toBe(TodoStatus::Open)
        ->and($todo->verified_by)->toBeNull();
});

test('vorstand can delete todo', function (): void {
    $vorstand = $this->actingMember('Vorstand');
    $todo = $this->createTodo($vorstand);
    Livewire::actingAs($vorstand)->test(TodoIndex::class)->call('deleteTodo', $todo->id)->assertHasNoErrors();
    $this->assertDatabaseMissing('todos', ['id' => $todo->id]);
});

test('admin can delete todo', function (): void {
    $admin = $this->actingMember('Admin');
    $creator = $this->actingMember('Vorstand');
    $todo = $this->createTodo($creator);
    Livewire::actingAs($admin)->test(TodoIndex::class)->call('deleteTodo', $todo->id)->assertHasNoErrors();
    $this->assertDatabaseMissing('todos', ['id' => $todo->id]);
});

test('member cannot delete todo', function (): void {
    $creator = $this->actingMember('Vorstand');
    $todo = $this->createTodo($creator);
    $member = $this->actingMember();
    Livewire::actingAs($member)->test(TodoIndex::class)->call('deleteTodo', $todo->id)->assertForbidden();
    $this->assertDatabaseHas('todos', ['id' => $todo->id]);
});

test('deleting verified todo removes user points', function (): void {
    $vorstand = $this->actingMember('Vorstand');
    $member = $this->actingMember();
    $todo = $this->createTodo($vorstand, ['assigned_to' => $member->id, 'status' => TodoStatus::Completed->value, 'completed_at' => now()]);
    // Verify first
    Livewire::actingAs($vorstand)->test(TodoIndex::class)->call('verify', $todo->id);
    $this->assertDatabaseHas('user_points', ['user_id' => $member->id, 'todo_id' => $todo->id]);
    // Then delete
    Livewire::actingAs($vorstand)->test(TodoIndex::class)->call('deleteTodo', $todo->id)->assertHasNoErrors();
    $this->assertDatabaseMissing('user_points', ['user_id' => $member->id, 'todo_id' => $todo->id]);
    $this->assertDatabaseMissing('todos', ['id' => $todo->id]);
});

test('deleting unverified todo does not affect points', function (): void {
    $vorstand = $this->actingMember('Vorstand');
    $todo = $this->createTodo($vorstand);
    $this->assertDatabaseMissing('user_points', ['todo_id' => $todo->id]);
    Livewire::actingAs($vorstand)->test(TodoIndex::class)->call('deleteTodo', $todo->id)->assertHasNoErrors();
    $this->assertDatabaseMissing('todos', ['id' => $todo->id]);
});

test('pending filter only returns completed tasks', function (): void {
    $admin = $this->actingMember('Admin');
    $assignee = $this->actingMember();
    $this->createTodo($admin, ['status' => TodoStatus::Completed->value, 'completed_at' => now(), 'assigned_to' => $assignee->id]);
    $this->createTodo($admin, ['status' => TodoStatus::Open->value]);
    Livewire::actingAs($admin)->test(TodoIndex::class, ['filter' => 'pending'])->assertSeeHtml('id="todo-pending-heading"')->assertOk();
});

test('all filter is default when query empty', function (): void {
    $member = $this->actingMember();
    Livewire::actingAs($member)->test(TodoIndex::class)->assertSet('filter', 'all');
});

test('assigned filter only renders assigned section', function (): void {
    $admin = $this->actingMember('Admin');
    $other = $this->actingMember();
    $this->createTodo($admin, ['status' => TodoStatus::Open->value]);
    $this->createTodo($admin, ['assigned_to' => $admin->id, 'status' => TodoStatus::Assigned->value]);
    $this->createTodo($admin, ['assigned_to' => $other->id, 'status' => TodoStatus::Completed->value, 'completed_at' => now()]);
    Livewire::actingAs($admin)->test(TodoIndex::class, ['filter' => 'assigned'])->assertSeeHtml('id="todo-assigned-heading"')->assertDontSeeHtml('id="todo-open-heading"')->assertDontSeeHtml('id="todo-progress-heading"')->assertDontSeeHtml('id="todo-pending-heading"');
});

test('open filter only renders open section', function (): void {
    $admin = $this->actingMember('Admin');
    $this->createTodo($admin, ['status' => TodoStatus::Open->value]);
    $this->createTodo($admin, ['assigned_to' => $admin->id, 'status' => TodoStatus::Assigned->value]);
    Livewire::actingAs($admin)->test(TodoIndex::class, ['filter' => 'open'])->assertSeeHtml('id="todo-open-heading"')->assertDontSeeHtml('id="todo-assigned-heading"')->assertDontSeeHtml('id="todo-progress-heading"');
});

test('pending filter only renders pending section', function (): void {
    $admin = $this->actingMember('Admin');
    $assignee = $this->actingMember();
    $this->createTodo($admin, ['status' => TodoStatus::Open->value]);
    $this->createTodo($admin, ['assigned_to' => $assignee->id, 'status' => TodoStatus::Completed->value, 'completed_at' => now()]);
    Livewire::actingAs($admin)->test(TodoIndex::class, ['filter' => 'pending'])->assertSeeHtml('id="todo-pending-heading"')->assertDontSeeHtml('id="todo-open-heading"')->assertDontSeeHtml('id="todo-assigned-heading"');
});

test('all filter renders all sections', function (): void {
    $admin = $this->actingMember('Admin');
    $other = $this->actingMember();
    $this->createTodo($admin, ['status' => TodoStatus::Open->value]);
    $this->createTodo($admin, ['assigned_to' => $admin->id, 'status' => TodoStatus::Assigned->value]);
    $this->createTodo($admin, ['assigned_to' => $other->id, 'status' => TodoStatus::Assigned->value]);
    $this->createTodo($admin, ['assigned_to' => $other->id, 'status' => TodoStatus::Completed->value, 'completed_at' => now()]);
    Livewire::actingAs($admin)->test(TodoIndex::class)->assertSeeHtml('id="todo-assigned-heading"')->assertSeeHtml('id="todo-open-heading"')->assertSeeHtml('id="todo-progress-heading"')->assertSeeHtml('id="todo-pending-heading"');
});

test('show displays todo and permissions', function (): void {
    $user = $this->actingMember();
    $todo = $this->createTodo($user);
    Livewire::actingAs($user)->test(TodoShow::class, ['todo' => $todo])->assertSeeText($todo->title)->assertOk();
});

test('show redirects when todo belongs to different team', function (): void {
    $member = $this->actingMember();
    $otherTeam = Team::factory()->create(['name' => 'Other Team', 'personal_team' => false]);
    $category = TodoCategory::first() ?? TodoCategory::create(['name' => 'Other', 'slug' => 'other']);
    $foreignTodo = Todo::create(['team_id' => $otherTeam->id, 'created_by' => $member->id, 'title' => 'Foreign Todo', 'description' => 'not accessible', 'points' => 4, 'category_id' => $category->id, 'status' => TodoStatus::Open->value]);
    Livewire::actingAs($member)->test(TodoShow::class, ['todo' => $foreignTodo])->assertRedirect(route('todos.index'));
});

test('show assign action', function (): void {
    $user = $this->actingMember();
    $todo = $this->createTodo($user);
    Livewire::actingAs($user)->test(TodoShow::class, ['todo' => $todo])->call('assign')->assertHasNoErrors();
    $todo->refresh();
    expect($todo->status)->toBe(TodoStatus::Assigned)
        ->and($todo->assigned_to)->toBe($user->id);
});

test('show complete action', function (): void {
    $user = $this->actingMember();
    $todo = $this->createTodo($user, ['assigned_to' => $user->id, 'status' => TodoStatus::Assigned->value]);
    Livewire::actingAs($user)->test(TodoShow::class, ['todo' => $todo])->call('complete')->assertHasNoErrors();
    $todo->refresh();
    expect($todo->status)->toBe(TodoStatus::Completed);
});

test('show verify action', function (): void {
    $assignee = $this->actingMember();
    $admin = $this->actingMember('Admin');
    $todo = $this->createTodo($admin, ['assigned_to' => $assignee->id, 'status' => TodoStatus::Completed->value, 'completed_at' => now()]);
    Livewire::actingAs($admin)->test(TodoShow::class, ['todo' => $todo])->call('verify')->assertHasNoErrors();
    $todo->refresh();
    expect($todo->status)->toBe(TodoStatus::Verified);
    $this->assertDatabaseHas('user_points', ['user_id' => $assignee->id, 'todo_id' => $todo->id]);
});

test('show release action', function (): void {
    $user = $this->actingMember();
    $todo = $this->createTodo($user, ['assigned_to' => $user->id, 'status' => TodoStatus::Assigned->value]);
    Livewire::actingAs($user)->test(TodoShow::class, ['todo' => $todo])->call('release')->assertHasNoErrors();
    $todo->refresh();
    expect($todo->assigned_to)->toBeNull()
        ->and($todo->status)->toBe(TodoStatus::Open);
});

test('show delete action', function (): void {
    $vorstand = $this->actingMember('Vorstand');
    $todo = $this->createTodo($vorstand);
    Livewire::actingAs($vorstand)->test(TodoShow::class, ['todo' => $todo])->call('deleteTodo')->assertRedirect(route('todos.index'));
    $this->assertDatabaseMissing('todos', ['id' => $todo->id]);
});

test('admin can view create form', function (): void {
    $admin = $this->actingMember('Admin');
    Livewire::actingAs($admin)->test(TodoForm::class)->assertSeeText('Neue Challenge erstellen')->assertOk();
});

test('regular member cannot view create form', function (): void {
    $member = $this->actingMember();
    Livewire::actingAs($member)->test(TodoForm::class)->assertForbidden();
});

test('store creates todo for admin', function (): void {
    $admin = $this->actingMember('Admin');
    $category = TodoCategory::first() ?? TodoCategory::create(['name' => 'Test', 'slug' => 'test']);
    Livewire::actingAs($admin)->test(TodoForm::class)->set('title', 'New Todo')->set('description', 'Desc')->set('points', 7)->set('category_id', $category->id)->call('save')->assertHasNoErrors()->assertRedirect(route('todos.index'));
    $this->assertDatabaseHas('todos', ['title' => 'New Todo', 'created_by' => $admin->id]);
});

test('store validation errors', function (): void {
    $admin = $this->actingMember('Admin');
    Livewire::actingAs($admin)->test(TodoForm::class)->set('title', '')->set('points', 0)->set('category_id', null)->call('save')->assertOnlyHasErrors(['title', 'points', 'category_id']);
});

test('creator can update todo', function (): void {
    $user = $this->actingMember('Admin');
    $todo = $this->createTodo($user);
    $category = TodoCategory::first();
    Livewire::actingAs($user)->test(TodoForm::class, ['todo' => $todo])->set('title', 'Updated')->set('description', 'New desc')->set('points', 10)->set('category_id', $category->id)->call('save')->assertHasNoErrors()->assertRedirect(route('todos.show', $todo));
    $todo->refresh();
    expect($todo->title)->toBe('Updated')
        ->and($todo->points)->toBe(10);
});

test('update validation errors', function (): void {
    $user = $this->actingMember('Admin');
    $todo = $this->createTodo($user);
    Livewire::actingAs($user)->test(TodoForm::class, ['todo' => $todo])->set('title', '')->set('points', 0)->set('category_id', null)->call('save')->assertOnlyHasErrors(['title', 'points', 'category_id']);
});

test('non creator cannot update todo', function (): void {
    $creator = $this->actingMember('Admin');
    $other = $this->actingMember();
    $todo = $this->createTodo($creator);
    Livewire::actingAs($other)->test(TodoForm::class, ['todo' => $todo])->assertForbidden();
});

test('edit page loads for creator', function (): void {
    $user = $this->actingMember('Admin');
    $todo = $this->createTodo($user);
    Livewire::actingAs($user)->test(TodoForm::class, ['todo' => $todo])->assertSeeText('Challenge bearbeiten')->assertOk();
});

test('index uses members team provider', function (): void {
    $team = Team::membersTeam();
    $this->mock(MembersTeamProvider::class, function ($mock) use ($team) {
        $mock->shouldReceive('getMembersTeamOrAbort')->andReturn($team);
    });
    $user = $this->createUserWithRole(Role::Mitglied);
    Livewire::actingAs($user)->test(TodoIndex::class)->assertOk();
});
