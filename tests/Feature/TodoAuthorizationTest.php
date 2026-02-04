<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\TodoCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TodoAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_cannot_access_create_page(): void
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => \App\Enums\Role::Mitglied->value]);
        $this->actingAs($user);

        $response = $this->get('/aufgaben/erstellen');

        $response->assertForbidden();
    }

    public function test_admin_can_access_create_page(): void
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => \App\Enums\Role::Admin->value]);
        $this->actingAs($user);

        TodoCategory::create(['name' => 'Test', 'slug' => 'test']);

        $response = $this->get('/aufgaben/erstellen');

        $response->assertOk();
        $response->assertViewIs('todos.create');
    }
}
