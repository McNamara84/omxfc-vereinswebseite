<?php

namespace Tests\Integration;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

#[CoversClass(User::class)]
class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_role_and_has_any_role(): void
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => Role::Admin->value]);

        $this->assertTrue($user->hasRole(Role::Admin));
        $this->assertTrue($user->hasAnyRole(Role::Admin, Role::Mitglied));
        $this->assertFalse($user->hasRole(Role::Mitglied));
        $this->assertFalse($user->hasAnyRole(Role::Mitglied, Role::Vorstand));
    }

    #[TestWith([null, false])]
    #[TestWith([Role::Anwaerter, false])]
    #[TestWith([Role::Mitwirkender, true])]
    #[TestWith([Role::Mitglied, true])]
    #[TestWith([Role::Ehrenmitglied, true])]
    #[TestWith([Role::Kassenwart, true])]
    #[TestWith([Role::Vorstand, true])]
    #[TestWith([Role::Admin, true])]
    public function test_profile_tour_eligibility_depends_on_the_members_team_role(?Role $role, bool $eligible): void
    {
        $membersTeam = Team::membersTeam();
        $otherTeam = Team::factory()->create(['name' => 'AG Test', 'personal_team' => false]);
        $user = User::factory()->create(['current_team_id' => $otherTeam->id]);
        if ($role !== null) {
            $membersTeam->users()->attach($user, ['role' => $role->value]);
        }
        // A different role in the selected team must neither grant nor revoke access.
        $otherRole = $eligible ? Role::Anwaerter : Role::Admin;
        $otherTeam->users()->attach($user, ['role' => $otherRole->value]);

        $this->assertSame($otherRole, $user->role());
        $this->assertSame($eligible, $user->canUseProfileTour());
    }
}
