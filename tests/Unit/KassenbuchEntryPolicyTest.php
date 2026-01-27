<?php

namespace Tests\Unit;

use App\Enums\KassenbuchEditReasonType;
use App\Enums\KassenbuchEntryType;
use App\Enums\Role;
use App\Models\KassenbuchEditRequest;
use App\Models\KassenbuchEntry;
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

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new KassenbuchEntryPolicy();
        $this->team = Team::membersTeam();
    }

    private function createUserWithRole(Role $role): User
    {
        $user = User::factory()->create(['current_team_id' => $this->team->id]);
        $this->team->users()->attach($user, ['role' => $role->value]);

        return $user;
    }

    private function createKassenbuchEntry(User $creator): KassenbuchEntry
    {
        return KassenbuchEntry::create([
            'team_id' => $this->team->id,
            'created_by' => $creator->id,
            'buchungsdatum' => now(),
            'betrag' => 50.00,
            'beschreibung' => 'Test-Eintrag',
            'typ' => KassenbuchEntryType::Einnahme->value,
        ]);
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

    // ==================== Request Edit Policy Tests ====================

    #[TestWith([Role::Kassenwart])]
    #[TestWith([Role::Admin])]
    public function test_request_edit_allows_kassenwart_and_admin(Role $role): void
    {
        $user = $this->createUserWithRole($role);
        $entry = $this->createKassenbuchEntry($user);

        $this->assertTrue($this->policy->requestEdit($user, $entry));
    }

    public function test_request_edit_denies_vorstand(): void
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $vorstand = $this->createUserWithRole(Role::Vorstand);
        $entry = $this->createKassenbuchEntry($kassenwart);

        $this->assertFalse($this->policy->requestEdit($vorstand, $entry));
    }

    public function test_request_edit_denies_mitglied(): void
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $mitglied = $this->createUserWithRole(Role::Mitglied);
        $entry = $this->createKassenbuchEntry($kassenwart);

        $this->assertFalse($this->policy->requestEdit($mitglied, $entry));
    }

    // Note: The test for "pending request exists" was removed because
    // this check was moved from Policy to Controller for better UX
    // (user-friendly error message instead of 403)

    // ==================== Edit Policy Tests ====================

    #[TestWith([Role::Kassenwart])]
    #[TestWith([Role::Admin])]
    public function test_edit_allows_with_approved_request(Role $role): void
    {
        $user = $this->createUserWithRole($role);
        $vorstand = $this->createUserWithRole(Role::Vorstand);
        $entry = $this->createKassenbuchEntry($user);

        // Create approved request
        KassenbuchEditRequest::create([
            'kassenbuch_entry_id' => $entry->id,
            'requested_by' => $user->id,
            'processed_by' => $vorstand->id,
            'reason_type' => KassenbuchEditReasonType::Tippfehler->value,
            'status' => KassenbuchEditRequest::STATUS_APPROVED,
            'processed_at' => now(),
        ]);

        $this->assertTrue($this->policy->edit($user, $entry));
    }

    public function test_edit_denies_without_approved_request(): void
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $entry = $this->createKassenbuchEntry($kassenwart);

        $this->assertFalse($this->policy->edit($kassenwart, $entry));
    }

    public function test_edit_denies_with_pending_request(): void
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $entry = $this->createKassenbuchEntry($kassenwart);

        KassenbuchEditRequest::create([
            'kassenbuch_entry_id' => $entry->id,
            'requested_by' => $kassenwart->id,
            'reason_type' => KassenbuchEditReasonType::Tippfehler->value,
            'status' => KassenbuchEditRequest::STATUS_PENDING,
        ]);

        $this->assertFalse($this->policy->edit($kassenwart, $entry));
    }

    public function test_edit_denies_vorstand_even_with_approved_request(): void
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $vorstand = $this->createUserWithRole(Role::Vorstand);
        $entry = $this->createKassenbuchEntry($kassenwart);

        KassenbuchEditRequest::create([
            'kassenbuch_entry_id' => $entry->id,
            'requested_by' => $kassenwart->id,
            'processed_by' => $vorstand->id,
            'reason_type' => KassenbuchEditReasonType::Tippfehler->value,
            'status' => KassenbuchEditRequest::STATUS_APPROVED,
            'processed_at' => now(),
        ]);

        $this->assertFalse($this->policy->edit($vorstand, $entry));
    }

    // ==================== Process Edit Request Policy Tests ====================

    #[TestWith([Role::Vorstand])]
    #[TestWith([Role::Admin])]
    public function test_process_edit_request_allows_vorstand_and_admin(Role $role): void
    {
        $user = $this->createUserWithRole($role);

        $this->assertTrue($this->policy->processEditRequest($user));
    }

    public function test_process_edit_request_denies_kassenwart(): void
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);

        $this->assertFalse($this->policy->processEditRequest($kassenwart));
    }

    public function test_process_edit_request_denies_mitglied(): void
    {
        $mitglied = $this->createUserWithRole(Role::Mitglied);

        $this->assertFalse($this->policy->processEditRequest($mitglied));
    }
}
