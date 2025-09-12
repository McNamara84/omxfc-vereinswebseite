<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;
use App\Models\TodoCategory;

class TodoAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_cannot_access_create_page(): void
    {
        $team = Team::membersTeam();
        $user = User::factory()->create();
        $team->users()->attach($user, ['role' => 'Mitglied']);
        $this->actingAs($user);

        $response = $this->get('/aufgaben/erstellen');

        $response->assertRedirect(route('todos.index', [], false));
        $response->assertSessionHas('error', 'Du hast keine Berechtigung, Challenges zu erstellen.');
    }

    public function test_admin_can_access_create_page(): void
    {
        $team = Team::membersTeam();
        $user = User::factory()->create();
        $team->users()->attach($user, ['role' => 'Admin']);
        $this->actingAs($user);

        TodoCategory::create(['name' => 'Test', 'slug' => 'test']);

        $response = $this->get('/aufgaben/erstellen');

        $response->assertOk();
        $response->assertViewIs('todos.create');
    }
}
