<?php

namespace Tests\Unit;

use App\Enums\AuktionsStatus;
use App\Enums\Role;
use App\Models\Auktion;
use App\Models\AuktionGebot;
use App\Policies\AuktionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

#[CoversClass(AuktionPolicy::class)]
class AuktionPolicyTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    private AuktionPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new AuktionPolicy;
    }

    #[TestWith([Role::Mitglied])]
    #[TestWith([Role::Ehrenmitglied])]
    #[TestWith([Role::Mitwirkender])]
    #[TestWith([Role::Vorstand])]
    #[TestWith([Role::Kassenwart])]
    #[TestWith([Role::Admin])]
    public function test_view_any_allows_all_non_applicant_member_roles(Role $role): void
    {
        $user = $this->createUserWithRole($role);

        $this->assertTrue($this->policy->viewAny($user));
    }

    public function test_view_any_denies_anwaerter(): void
    {
        $user = $this->createUserWithRole(Role::Anwaerter);

        $this->assertFalse($this->policy->viewAny($user));
    }

    #[TestWith([Role::Admin])]
    #[TestWith([Role::Vorstand])]
    #[TestWith([Role::Kassenwart])]
    public function test_manage_allows_governance_roles(Role $role): void
    {
        $user = $this->createUserWithRole($role);

        $this->assertTrue($this->policy->manage($user));
    }

    public function test_manage_denies_regular_members(): void
    {
        $user = $this->createUserWithRole(Role::Mitglied);

        $this->assertFalse($this->policy->manage($user));
    }

    #[TestWith([Role::Mitglied])]
    #[TestWith([Role::Ehrenmitglied])]
    #[TestWith([Role::Mitwirkender])]
    public function test_bid_allows_configured_bidding_roles(Role $role): void
    {
        $user = $this->createUserWithRole($role);
        $auktion = Auktion::factory()->create();

        $this->assertTrue($this->policy->bid($user, $auktion));
    }

    #[TestWith([Role::Admin])]
    #[TestWith([Role::Vorstand])]
    #[TestWith([Role::Kassenwart])]
    #[TestWith([Role::Anwaerter])]
    public function test_bid_denies_non_bidding_roles(Role $role): void
    {
        $user = $this->createUserWithRole($role);
        $auktion = Auktion::factory()->create();

        $this->assertFalse($this->policy->bid($user, $auktion));
    }

    public function test_bid_denies_closed_auctions_even_for_regular_member(): void
    {
        $user = $this->createUserWithRole(Role::Mitglied);
        $auktion = Auktion::factory()->create([
            'status' => AuktionsStatus::Verkauft,
        ]);

        $this->assertFalse($this->policy->bid($user, $auktion));
    }

    public function test_call_allows_only_vorstand(): void
    {
        $vorstand = $this->createUserWithRole(Role::Vorstand);
        $admin = $this->createUserWithRole(Role::Admin);
        $auktion = Auktion::factory()->create();

        $this->assertTrue($this->policy->call($vorstand, $auktion));
        $this->assertFalse($this->policy->call($admin, $auktion));
    }

    public function test_delete_denies_auctions_with_existing_bids(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);
        $bieter = $this->createUserWithRole(Role::Mitglied);
        $auktion = Auktion::factory()->create();

        AuktionGebot::factory()->for($auktion)->for($bieter)->create([
            'bieter_name' => $bieter->name,
        ]);

        $this->assertFalse($this->policy->delete($admin, $auktion->fresh('gebote')));
    }
}
