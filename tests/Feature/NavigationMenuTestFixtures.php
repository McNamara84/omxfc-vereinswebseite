<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUserWithRole;

trait NavigationMenuTestFixtures
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    private function addAgRollenspielMembership(User $user): User
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id, 'name' => 'AG Rollenspiel', 'personal_team' => false]);
        $team->users()->attach($user, ['role' => Role::Mitglied->value]);

        return $user->refresh();
    }

    private function createManagementUserWithDifferentCurrentTeam(Role $role): User
    {
        $managementTeam = Team::membersTeam() ?? Team::factory()->create(['name' => 'Mitglieder', 'personal_team' => false]);
        $user = User::factory()->create(['current_team_id' => $managementTeam->id]);
        $managementTeam->users()->attach($user, ['role' => $role->value]);
        $otherTeam = Team::factory()->create(['user_id' => $user->id, 'name' => 'Nebenverein', 'personal_team' => false]);
        $otherTeam->users()->attach($user, ['role' => Role::Mitglied->value]);
        $user->forceFill(['current_team_id' => $otherTeam->id])->save();

        return $user->refresh();
    }

    /**
     * @param  array<string, mixed>  $navigation
     * @return array<int, string>
     */
    private function sectionItemTitles(array $navigation, string $sectionTitle): array
    {
        $section = collect($navigation['sections'] ?? [])->firstWhere('title', $sectionTitle);

        return array_column($section['items'] ?? [], 'title');
    }
}
