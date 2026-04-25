<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\TodoForm;
use App\Models\Team;
use App\Models\TodoCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TodoAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_cannot_access_create_page(): void
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => Role::Mitglied->value]);

        Livewire::actingAs($user)
            ->test(TodoForm::class)
            ->assertForbidden();
    }

    public function test_admin_can_access_create_page(): void
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => Role::Admin->value]);

        TodoCategory::create(['name' => 'Test', 'slug' => 'test']);

        Livewire::actingAs($user)
            ->test(TodoForm::class)
            ->assertSeeText('Neue Challenge erstellen')
            ->assertOk();
    }
}
