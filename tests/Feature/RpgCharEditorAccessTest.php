<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RpgCharEditorAccessTest extends TestCase
{
    use RefreshDatabase;

    private function createMember(string $role = 'Mitglied'): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role]);
        return $user;
    }

    public function test_admin_can_access_editor(): void
    {
        $admin = $this->createMember('Admin');

        $this->actingAs($admin)
            ->get('/rpg/char-editor')
            ->assertOk()
            ->assertSee('Charakter-Editor');
    }

    public function test_non_admin_is_forbidden_from_editor(): void
    {
        $user = $this->createMember();

        $this->actingAs($user)
            ->get('/rpg/char-editor')
            ->assertForbidden();
    }
}

