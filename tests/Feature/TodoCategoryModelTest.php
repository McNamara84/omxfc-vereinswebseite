<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\Todo;
use App\Models\TodoCategory;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TodoCategoryModelTest extends TestCase
{
    use RefreshDatabase;

    private function createMember(): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => \App\Enums\Role::Mitglied->value]);

        return $user;
    }

    public function test_category_can_be_created(): void
    {
        $category = TodoCategory::create([
            'name' => 'General',
            'slug' => 'general',
        ]);

        $this->assertDatabaseHas('todo_categories', [
            'id' => $category->id,
            'name' => 'General',
            'slug' => 'general',
        ]);
    }

    public function test_mass_assignment_protects_id(): void
    {
        $category = TodoCategory::create([
            'id' => 999,
            'name' => 'Protected',
            'slug' => 'protected',
        ]);

        $category->refresh();

        $this->assertNotEquals(999, $category->id);
        $this->assertSame('Protected', $category->name);
    }

    public function test_category_has_many_todos(): void
    {
        $user = $this->createMember();
        $team = $user->currentTeam;
        $category = TodoCategory::create(['name' => 'General', 'slug' => 'general']);

        $todo1 = Todo::create([
            'team_id' => $team->id,
            'created_by' => $user->id,
            'title' => 'First',
            'points' => 1,
            'category_id' => $category->id,
        ]);
        $todo2 = Todo::create([
            'team_id' => $team->id,
            'created_by' => $user->id,
            'title' => 'Second',
            'points' => 2,
            'category_id' => $category->id,
        ]);

        $this->assertCount(2, $category->todos);
        $this->assertTrue($category->todos->contains($todo1));
        $this->assertTrue($category->todos->contains($todo2));
    }

    public function test_slug_is_unique(): void
    {
        TodoCategory::create(['name' => 'General', 'slug' => 'unique']);

        $this->expectException(QueryException::class);
        TodoCategory::create(['name' => 'Other', 'slug' => 'unique']);
    }
}
