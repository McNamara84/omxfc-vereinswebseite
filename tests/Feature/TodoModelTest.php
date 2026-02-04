<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\Todo;
use App\Models\TodoCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TodoModelTest extends TestCase
{
    use RefreshDatabase;

    private function createMember(string $role = 'Mitglied'): User
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

        return Todo::create(array_merge([
            'team_id' => $team->id,
            'created_by' => $creator->id,
            'title' => 'Todo',
            'points' => 5,
            'category_id' => $category->id,
            'status' => 'open',
        ], $attrs));
    }

    public function test_can_be_assigned_to_checks_membership_role(): void
    {
        $member = $this->createMember();
        $todo = $this->createTodo($member);

        $this->assertTrue($todo->canBeAssignedTo($member));

        $outsider = User::factory()->create();
        $this->assertFalse($todo->canBeAssignedTo($outsider));
    }

    public function test_can_be_created_by_requires_elevated_role(): void
    {
        $admin = $this->createMember('Admin');
        $todo = $this->createTodo($admin);

        $this->assertTrue($todo->canBeCreatedBy($admin));

        $member = $this->createMember();
        $this->assertFalse($todo->canBeCreatedBy($member));
    }

    public function test_can_be_verified_by_only_admin_or_similar(): void
    {
        $admin = $this->createMember('Admin');
        $todo = $this->createTodo($admin);

        $this->assertTrue($todo->canBeVerifiedBy($admin));

        $member = $this->createMember();
        $this->assertFalse($todo->canBeVerifiedBy($member));
    }
}
