<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesFantreffenFormToken;
use Tests\Concerns\CreatesUserWithRole;

trait VeranstaltungAnmeldungTestFixtures
{
    use CreatesFantreffenFormToken;
    use CreatesUserWithRole;
    use RefreshDatabase;

    private function createManagementUserWithDifferentCurrentTeam(Role $role): User
    {
        $user = $this->createUserWithRole($role);
        $otherTeam = Team::factory()->create(['user_id' => $user->id, 'name' => 'Nebenverein', 'personal_team' => false]);
        $otherTeam->users()->attach($user, ['role' => Role::Mitglied->value]);
        $user->forceFill(['current_team_id' => $otherTeam->id])->save();

        return $user->refresh();
    }
}
