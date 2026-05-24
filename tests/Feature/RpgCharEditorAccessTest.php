<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RpgCharEditorAccessTest extends TestCase
{
    use RefreshDatabase;

    private function createMember(Role|string $role = Role::Mitglied): User
    {
        $team = Team::membersTeam();

        if (! $team) {
            $team = Team::factory()->create(['name' => 'Mitglieder', 'personal_team' => false]);
        }

        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role instanceof Role ? $role->value : $role]);

        return $user->refresh();
    }

    private function addAgRollenspielMembership(User $user): User
    {
        $owner = User::factory()->create();

        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'name' => 'AG Rollenspiel',
            'personal_team' => false,
        ]);
        $team->users()->attach($user, ['role' => Role::Mitglied->value]);

        return $user->refresh();
    }

    private function createManagementUserWithDifferentCurrentTeam(Role $role): User
    {
        $managementTeam = Team::membersTeam() ?? Team::factory()->create([
            'name' => 'Mitglieder',
            'personal_team' => false,
        ]);

        $user = User::factory()->create(['current_team_id' => $managementTeam->id]);
        $managementTeam->users()->attach($user, ['role' => $role->value]);

        $otherTeam = Team::factory()->create([
            'user_id' => $user->id,
            'name' => 'Nebenverein',
            'personal_team' => false,
        ]);
        $otherTeam->users()->attach($user, ['role' => Role::Mitglied->value]);

        $user->forceFill(['current_team_id' => $otherTeam->id])->save();

        return $user->refresh();
    }

    public function test_ag_rollenspiel_member_can_access_editor(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        $this->actingAs($member)
            ->get('/rpg/char-editor')
            ->assertOk()
            ->assertSee('Charakter-Editor');
    }

    public function test_global_admin_can_access_editor_with_different_current_team(): void
    {
        $admin = $this->createManagementUserWithDifferentCurrentTeam(Role::Admin);

        $this->actingAs($admin)
            ->get('/rpg/char-editor')
            ->assertOk()
            ->assertSee('Charakter-Editor');
    }

    public function test_member_without_ag_rollenspiel_is_forbidden_from_editor(): void
    {
        $user = $this->createMember();

        $this->actingAs($user)
            ->get('/rpg/char-editor')
            ->assertForbidden();
    }
}
