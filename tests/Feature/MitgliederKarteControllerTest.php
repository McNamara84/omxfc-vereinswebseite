<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TodoStatus;
use App\Livewire\TodoIndex;
use Livewire\Livewire;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(MitgliederKarteControllerTestFixtures::class);

test('member can assign todo', function (): void {
    $user = $this->actingMember();
    $todo = $this->createTodo($user);
    Livewire::actingAs($user)->test(TodoIndex::class)->call('assign', $todo->id)->assertHasNoErrors();
    $todo->refresh();
    expect($todo->status)->toBe(TodoStatus::Assigned)
        ->and($todo->assigned_to)->toBe($user->id);
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
});

test('assigned user can release todo', function (): void {
    $user = $this->actingMember();
    $todo = $this->createTodo($user, ['assigned_to' => $user->id, 'status' => TodoStatus::Assigned->value]);
    Livewire::actingAs($user)->test(TodoIndex::class)->call('release', $todo->id)->assertHasNoErrors();
    $todo->refresh();
    expect($todo->assigned_to)->toBeNull()
        ->and($todo->status)->toBe(TodoStatus::Open);
});
