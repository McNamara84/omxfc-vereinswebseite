<?php

namespace Tests\Unit;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use App\Policies\KassenbuchEntryPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(KassenbuchEntryPolicy::class)]
class KassenbuchEntryPolicyTest extends TestCase
{
    use RefreshDatabase;

    private KassenbuchEntryPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new KassenbuchEntryPolicy();
    }

    private function createUserWithRole(Role $role): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role->value]);

        return $user;
    }

    public function test_view_all_allows_finance_roles(): void
    {
        foreach ([Role::Vorstand, Role::Admin, Role::Kassenwart] as $role) {
            $user = $this->createUserWithRole($role);
            $this->assertTrue($this->policy->viewAll($user));
        }
    }

    public function test_view_all_denies_regular_member(): void
    {
        $user = $this->createUserWithRole(Role::Mitglied);
        $this->assertFalse($this->policy->viewAll($user));
    }

    public function test_manage_allows_kassenwart_and_admin(): void
    {
        foreach ([Role::Kassenwart, Role::Admin] as $role) {
            $user = $this->createUserWithRole($role);
            $this->assertTrue($this->policy->manage($user));
        }
    }

    public function test_manage_denies_vorstand_role(): void
    {
        $user = $this->createUserWithRole(Role::Vorstand);
        $this->assertFalse($this->policy->manage($user));
    }

    public function test_manage_denies_regular_members(): void
    {
        $user = $this->createUserWithRole(Role::Mitglied);
        $this->assertFalse($this->policy->manage($user));
    }
}
