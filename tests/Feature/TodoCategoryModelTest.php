<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Todo;
use App\Models\TodoCategory;
use Illuminate\Database\QueryException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(TodoCategoryModelTestFixtures::class);

test('category can be created', function (): void {
    $category = TodoCategory::create(['name' => 'General', 'slug' => 'general']);
    $this->assertDatabaseHas('todo_categories', ['id' => $category->id, 'name' => 'General', 'slug' => 'general']);
});

test('mass assignment protects id', function (): void {
    $category = TodoCategory::create(['id' => 999, 'name' => 'Protected', 'slug' => 'protected']);
    $category->refresh();
    expect($category->id)->not->toEqual(999)
        ->and($category->name)->toBe('Protected');
});

test('category has many todos', function (): void {
    $user = $this->createMember();
    $team = $user->currentTeam;
    $category = TodoCategory::create(['name' => 'General', 'slug' => 'general']);
    $todo1 = Todo::create(['team_id' => $team->id, 'created_by' => $user->id, 'title' => 'First', 'points' => 1, 'category_id' => $category->id]);
    $todo2 = Todo::create(['team_id' => $team->id, 'created_by' => $user->id, 'title' => 'Second', 'points' => 2, 'category_id' => $category->id]);
    expect($category->todos)->toHaveCount(2)
        ->and($category->todos->contains($todo1))->toBeTrue()
        ->and($category->todos->contains($todo2))->toBeTrue();
});

test('slug is unique', function (): void {
    TodoCategory::create(['name' => 'General', 'slug' => 'unique']);
    expect(fn () => TodoCategory::create(['name' => 'Other', 'slug' => 'unique']))->toThrow(QueryException::class);
});
