<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TodoStatus;
use App\Models\Team;
use App\Models\Todo;
use App\Models\TodoCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait MitgliederKarteControllerTestFixtures
{
    use RefreshDatabase;

    private function actingMember(string $role = 'Mitglied'): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role]);

        return $user;
    }

    private function createTodo(User $creator, array $attrs = []): Todo
    {
        $team = Team::membersTeam();
        $category = TodoCategory::first() ?? TodoCategory::create(['name' => 'Test', 'slug' => 'test']);

        return Todo::create(array_merge(['team_id' => $team->id, 'created_by' => $creator->id, 'title' => 'Todo', 'description' => 'desc', 'points' => 5, 'category_id' => $category->id, 'status' => TodoStatus::Open->value], $attrs));
    }
}
