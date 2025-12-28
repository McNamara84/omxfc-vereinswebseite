<?php

namespace Tests\Unit;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use App\Policies\KassenbuchEntryPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
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

    #[TestWith([Role::Vorstand])]
    #[TestWith([Role::Admin])]
    #[TestWith([Role::Kassenwart])]
    public function test_view_all_allows_finance_roles(Role $role): void
    {
        $user = $this->createUserWithRole($role);
        $this->assertTrue($this->policy->viewAll($user));
    }

    public function test_view_all_denies_regular_member(): void
    {
        $user = $this->createUserWithRole(Role::Mitglied);
        $this->assertFalse($this->policy->viewAll($user));
    }

    #[TestWith([Role::Kassenwart])]
    #[TestWith([Role::Admin])]
    public function test_manage_allows_kassenwart_and_admin(Role $role): void
    {
        $user = $this->createUserWithRole($role);
        $this->assertTrue($this->policy->manage($user));
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
