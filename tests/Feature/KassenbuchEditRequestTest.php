<?php

namespace Tests\Feature;

use App\Enums\KassenbuchEditReasonType;
use App\Enums\KassenbuchEntryType;
use App\Enums\Role;
use App\Models\KassenbuchEditRequest;
use App\Models\KassenbuchEntry;
use App\Models\Kassenstand;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(KassenbuchEditRequest::class)]
class KassenbuchEditRequestTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        $this->team = Team::membersTeam();

        // Create initial kassenstand
        Kassenstand::create([
            'team_id' => $this->team->id,
            'betrag' => 100.00,
            'letzte_aktualisierung' => now(),
        ]);
    }

    private function createUserWithRole(Role $role): User
    {
        $user = User::factory()->create(['current_team_id' => $this->team->id]);
        $this->team->users()->attach($user, ['role' => $role->value]);

        return $user;
    }

    private function createKassenbuchEntry(User $creator, float $betrag = 50.00): KassenbuchEntry
    {
        return KassenbuchEntry::create([
            'team_id' => $this->team->id,
            'created_by' => $creator->id,
            'buchungsdatum' => now(),
            'betrag' => $betrag,
            'beschreibung' => 'Test-Eintrag',
            'typ' => KassenbuchEntryType::Einnahme->value,
        ]);
    }

    // ==================== Request Edit Tests ====================

    public function test_kassenwart_can_request_edit(): void
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $entry = $this->createKassenbuchEntry($kassenwart);

        $response = $this->actingAs($kassenwart)
            ->post("/kassenbuch/eintrag/{$entry->id}/bearbeitung-anfragen", [
                'reason_type' => KassenbuchEditReasonType::Tippfehler->value,
                'reason_text' => null,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Bearbeitungsanfrage wurde gestellt.');

        $this->assertDatabaseHas('kassenbuch_edit_requests', [
            'kassenbuch_entry_id' => $entry->id,
            'requested_by' => $kassenwart->id,
            'reason_type' => 'tippfehler',
            'status' => 'pending',
        ]);
    }

    public function test_admin_can_request_edit(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);
        $entry = $this->createKassenbuchEntry($admin);

        $response = $this->actingAs($admin)
            ->post("/kassenbuch/eintrag/{$entry->id}/bearbeitung-anfragen", [
                'reason_type' => KassenbuchEditReasonType::FalscherBetrag->value,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('kassenbuch_edit_requests', [
            'kassenbuch_entry_id' => $entry->id,
            'status' => 'pending',
        ]);
    }

    public function test_mitglied_cannot_request_edit(): void
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $mitglied = $this->createUserWithRole(Role::Mitglied);
        $entry = $this->createKassenbuchEntry($kassenwart);

        $response = $this->actingAs($mitglied)
            ->post("/kassenbuch/eintrag/{$entry->id}/bearbeitung-anfragen", [
                'reason_type' => KassenbuchEditReasonType::Tippfehler->value,
            ]);

        $response->assertForbidden();
    }

    public function test_vorstand_cannot_request_edit(): void
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $vorstand = $this->createUserWithRole(Role::Vorstand);
        $entry = $this->createKassenbuchEntry($kassenwart);

        $response = $this->actingAs($vorstand)
            ->post("/kassenbuch/eintrag/{$entry->id}/bearbeitung-anfragen", [
                'reason_type' => KassenbuchEditReasonType::Tippfehler->value,
            ]);

        $response->assertForbidden();
    }

    public function test_cannot_request_edit_when_pending_exists(): void
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $entry = $this->createKassenbuchEntry($kassenwart);

        // First request
        KassenbuchEditRequest::create([
            'kassenbuch_entry_id' => $entry->id,
            'requested_by' => $kassenwart->id,
            'reason_type' => KassenbuchEditReasonType::Tippfehler->value,
            'status' => KassenbuchEditRequest::STATUS_PENDING,
        ]);

        // Second request should fail with error
        $response = $this->actingAs($kassenwart)
            ->post("/kassenbuch/eintrag/{$entry->id}/bearbeitung-anfragen", [
                'reason_type' => KassenbuchEditReasonType::FalscherBetrag->value,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('entry');
    }

    public function test_cannot_request_edit_when_approved_exists(): void
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $vorstand = $this->createUserWithRole(Role::Vorstand);
        $entry = $this->createKassenbuchEntry($kassenwart);

        // Create approved request
        KassenbuchEditRequest::create([
            'kassenbuch_entry_id' => $entry->id,
            'requested_by' => $kassenwart->id,
            'processed_by' => $vorstand->id,
            'reason_type' => KassenbuchEditReasonType::Tippfehler->value,
            'status' => KassenbuchEditRequest::STATUS_APPROVED,
            'processed_at' => now(),
        ]);

        // New request should fail
        $response = $this->actingAs($kassenwart)
            ->post("/kassenbuch/eintrag/{$entry->id}/bearbeitung-anfragen", [
                'reason_type' => KassenbuchEditReasonType::FalscherBetrag->value,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('entry');
    }

    public function test_sonstiges_requires_reason_text(): void
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $entry = $this->createKassenbuchEntry($kassenwart);

        $response = $this->actingAs($kassenwart)
            ->post("/kassenbuch/eintrag/{$entry->id}/bearbeitung-anfragen", [
                'reason_type' => KassenbuchEditReasonType::Sonstiges->value,
                'reason_text' => '',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('reason_text');
    }

    public function test_sonstiges_rejects_whitespace_only_reason_text(): void
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $entry = $this->createKassenbuchEntry($kassenwart);

        $response = $this->actingAs($kassenwart)
            ->post("/kassenbuch/eintrag/{$entry->id}/bearbeitung-anfragen", [
                'reason_type' => KassenbuchEditReasonType::Sonstiges->value,
                'reason_text' => '   ',  // Only whitespace
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('reason_text');
    }

    public function test_sonstiges_with_reason_text_succeeds(): void
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $entry = $this->createKassenbuchEntry($kassenwart);

        $response = $this->actingAs($kassenwart)
            ->post("/kassenbuch/eintrag/{$entry->id}/bearbeitung-anfragen", [
                'reason_type' => KassenbuchEditReasonType::Sonstiges->value,
                'reason_text' => 'Spezielle Korrektur erforderlich',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('kassenbuch_edit_requests', [
            'kassenbuch_entry_id' => $entry->id,
            'reason_type' => 'sonstiges',
            'reason_text' => 'Spezielle Korrektur erforderlich',
        ]);
    }

    // ==================== Approve/Reject Tests ====================

    public function test_vorstand_can_approve_edit_request(): void
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $vorstand = $this->createUserWithRole(Role::Vorstand);
        $entry = $this->createKassenbuchEntry($kassenwart);

        $editRequest = KassenbuchEditRequest::create([
            'kassenbuch_entry_id' => $entry->id,
            'requested_by' => $kassenwart->id,
            'reason_type' => KassenbuchEditReasonType::Tippfehler->value,
            'status' => KassenbuchEditRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($vorstand)
            ->post("/kassenbuch/anfrage/{$editRequest->id}/freigeben");

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Bearbeitung wurde freigegeben.');

        $editRequest->refresh();
        $this->assertEquals(KassenbuchEditRequest::STATUS_APPROVED, $editRequest->status);
        $this->assertEquals($vorstand->id, $editRequest->processed_by);
        $this->assertNotNull($editRequest->processed_at);
    }

    public function test_admin_can_approve_edit_request(): void
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $admin = $this->createUserWithRole(Role::Admin);
        $entry = $this->createKassenbuchEntry($kassenwart);

        $editRequest = KassenbuchEditRequest::create([
            'kassenbuch_entry_id' => $entry->id,
            'requested_by' => $kassenwart->id,
            'reason_type' => KassenbuchEditReasonType::Tippfehler->value,
            'status' => KassenbuchEditRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($admin)
            ->post("/kassenbuch/anfrage/{$editRequest->id}/freigeben");

        $response->assertRedirect();
        $editRequest->refresh();
        $this->assertEquals(KassenbuchEditRequest::STATUS_APPROVED, $editRequest->status);
    }

    public function test_kassenwart_cannot_approve_edit_request(): void
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $entry = $this->createKassenbuchEntry($kassenwart);

        $editRequest = KassenbuchEditRequest::create([
            'kassenbuch_entry_id' => $entry->id,
            'requested_by' => $kassenwart->id,
            'reason_type' => KassenbuchEditReasonType::Tippfehler->value,
            'status' => KassenbuchEditRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($kassenwart)
            ->post("/kassenbuch/anfrage/{$editRequest->id}/freigeben");

        $response->assertForbidden();
    }

    public function test_vorstand_can_reject_edit_request_with_reason(): void
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $vorstand = $this->createUserWithRole(Role::Vorstand);
        $entry = $this->createKassenbuchEntry($kassenwart);

        $editRequest = KassenbuchEditRequest::create([
            'kassenbuch_entry_id' => $entry->id,
            'requested_by' => $kassenwart->id,
            'reason_type' => KassenbuchEditReasonType::Tippfehler->value,
            'status' => KassenbuchEditRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($vorstand)
            ->post("/kassenbuch/anfrage/{$editRequest->id}/ablehnen", [
                'rejection_reason' => 'Eintrag ist korrekt',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Bearbeitungsanfrage wurde abgelehnt.');

        $editRequest->refresh();
        $this->assertEquals(KassenbuchEditRequest::STATUS_REJECTED, $editRequest->status);
        $this->assertEquals('Eintrag ist korrekt', $editRequest->rejection_reason);
        $this->assertEquals($vorstand->id, $editRequest->processed_by);
    }

    public function test_vorstand_can_reject_without_reason(): void
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $vorstand = $this->createUserWithRole(Role::Vorstand);
        $entry = $this->createKassenbuchEntry($kassenwart);

        $editRequest = KassenbuchEditRequest::create([
            'kassenbuch_entry_id' => $entry->id,
            'requested_by' => $kassenwart->id,
            'reason_type' => KassenbuchEditReasonType::Tippfehler->value,
            'status' => KassenbuchEditRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($vorstand)
            ->post("/kassenbuch/anfrage/{$editRequest->id}/ablehnen");

        $response->assertRedirect();
        $editRequest->refresh();
        $this->assertEquals(KassenbuchEditRequest::STATUS_REJECTED, $editRequest->status);
        $this->assertNull($editRequest->rejection_reason);
    }

    // ==================== Edit Entry Tests ====================

    public function test_kassenwart_can_edit_after_approval(): void
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $vorstand = $this->createUserWithRole(Role::Vorstand);
        $entry = $this->createKassenbuchEntry($kassenwart, 50.00);

        // Create approved request
        KassenbuchEditRequest::create([
            'kassenbuch_entry_id' => $entry->id,
            'requested_by' => $kassenwart->id,
            'processed_by' => $vorstand->id,
            'reason_type' => KassenbuchEditReasonType::FalscherBetrag->value,
            'reason_text' => 'Betrag war 75€',
            'status' => KassenbuchEditRequest::STATUS_APPROVED,
            'processed_at' => now(),
        ]);

        $response = $this->actingAs($kassenwart)
            ->put("/kassenbuch/eintrag/{$entry->id}", [
                'buchungsdatum' => '2025-01-15',
                'betrag' => 75.00,
                'beschreibung' => 'Korrigierter Test-Eintrag',
                'typ' => KassenbuchEntryType::Einnahme->value,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Kassenbucheintrag wurde aktualisiert.');

        $entry->refresh();
        $this->assertEquals(75.00, $entry->betrag);
        $this->assertEquals('Korrigierter Test-Eintrag', $entry->beschreibung);
        $this->assertEquals($kassenwart->id, $entry->last_edited_by);
        $this->assertNotNull($entry->last_edited_at);
        $this->assertStringContainsString('Falscher Betrag', $entry->last_edit_reason);

        // Edit request should be deleted
        $this->assertDatabaseMissing('kassenbuch_edit_requests', [
            'kassenbuch_entry_id' => $entry->id,
        ]);
    }

    public function test_kassenwart_cannot_edit_without_approval(): void
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $entry = $this->createKassenbuchEntry($kassenwart);

        $response = $this->actingAs($kassenwart)
            ->put("/kassenbuch/eintrag/{$entry->id}", [
                'buchungsdatum' => '2025-01-15',
                'betrag' => 75.00,
                'beschreibung' => 'Korrigierter Test-Eintrag',
                'typ' => KassenbuchEntryType::Einnahme->value,
            ]);

        $response->assertForbidden();
    }

    public function test_kassenwart_cannot_edit_with_pending_request(): void
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $entry = $this->createKassenbuchEntry($kassenwart);

        // Create pending request (not approved)
        KassenbuchEditRequest::create([
            'kassenbuch_entry_id' => $entry->id,
            'requested_by' => $kassenwart->id,
            'reason_type' => KassenbuchEditReasonType::Tippfehler->value,
            'status' => KassenbuchEditRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($kassenwart)
            ->put("/kassenbuch/eintrag/{$entry->id}", [
                'buchungsdatum' => '2025-01-15',
                'betrag' => 75.00,
                'beschreibung' => 'Korrigierter Test-Eintrag',
                'typ' => KassenbuchEntryType::Einnahme->value,
            ]);

        $response->assertForbidden();
    }

    public function test_edit_updates_kassenstand_correctly(): void
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $vorstand = $this->createUserWithRole(Role::Vorstand);

        // Setze Kassenstand auf 100
        Kassenstand::where('team_id', $this->team->id)->update(['betrag' => 100.00]);

        // Erstelle Eintrag mit 50€ Einnahme
        $entry = $this->createKassenbuchEntry($kassenwart, 50.00);

        KassenbuchEditRequest::create([
            'kassenbuch_entry_id' => $entry->id,
            'requested_by' => $kassenwart->id,
            'processed_by' => $vorstand->id,
            'reason_type' => KassenbuchEditReasonType::FalscherBetrag->value,
            'status' => KassenbuchEditRequest::STATUS_APPROVED,
            'processed_at' => now(),
        ]);

        // Ändere von 50 auf 75 (Differenz von +25)
        $this->actingAs($kassenwart)
            ->put("/kassenbuch/eintrag/{$entry->id}", [
                'buchungsdatum' => now()->format('Y-m-d'),
                'betrag' => 75.00,
                'beschreibung' => 'Korrigiert',
                'typ' => KassenbuchEntryType::Einnahme->value,
            ]);

        $kassenstand = Kassenstand::where('team_id', $this->team->id)->first();
        // 100 - 50 (alt) + 75 (neu) = 125
        $this->assertEquals(125.00, $kassenstand->betrag);
    }

    public function test_edit_from_einnahme_to_ausgabe_updates_kassenstand(): void
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $vorstand = $this->createUserWithRole(Role::Vorstand);

        // Setze Kassenstand auf 100
        Kassenstand::where('team_id', $this->team->id)->update(['betrag' => 100.00]);

        // Erstelle Eintrag mit 50€ Einnahme
        $entry = $this->createKassenbuchEntry($kassenwart, 50.00);

        KassenbuchEditRequest::create([
            'kassenbuch_entry_id' => $entry->id,
            'requested_by' => $kassenwart->id,
            'processed_by' => $vorstand->id,
            'reason_type' => KassenbuchEditReasonType::FalscherTyp->value,
            'status' => KassenbuchEditRequest::STATUS_APPROVED,
            'processed_at' => now(),
        ]);

        // Ändere von Einnahme 50 zu Ausgabe 50
        $this->actingAs($kassenwart)
            ->put("/kassenbuch/eintrag/{$entry->id}", [
                'buchungsdatum' => now()->format('Y-m-d'),
                'betrag' => 50.00,
                'beschreibung' => 'Korrigiert',
                'typ' => KassenbuchEntryType::Ausgabe->value,
            ]);

        $kassenstand = Kassenstand::where('team_id', $this->team->id)->first();
        // 100 - 50 (alte Einnahme) + (-50) (neue Ausgabe) = 0
        $this->assertEquals(0.00, $kassenstand->betrag);

        $entry->refresh();
        $this->assertEquals(-50.00, $entry->betrag);
    }

    // ==================== Index View Tests ====================

    public function test_vorstand_sees_pending_edit_requests(): void
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $vorstand = $this->createUserWithRole(Role::Vorstand);
        $entry = $this->createKassenbuchEntry($kassenwart);

        KassenbuchEditRequest::create([
            'kassenbuch_entry_id' => $entry->id,
            'requested_by' => $kassenwart->id,
            'reason_type' => KassenbuchEditReasonType::Tippfehler->value,
            'status' => KassenbuchEditRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($vorstand)->get('/kassenbuch');

        $response->assertOk();
        $response->assertViewHas('pendingEditRequests');
        $this->assertCount(1, $response->viewData('pendingEditRequests'));
    }

    public function test_kassenwart_does_not_see_pending_requests_section(): void
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $entry = $this->createKassenbuchEntry($kassenwart);

        KassenbuchEditRequest::create([
            'kassenbuch_entry_id' => $entry->id,
            'requested_by' => $kassenwart->id,
            'reason_type' => KassenbuchEditReasonType::Tippfehler->value,
            'status' => KassenbuchEditRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($kassenwart)->get('/kassenbuch');

        $response->assertOk();
        $this->assertNull($response->viewData('pendingEditRequests'));
    }

    public function test_index_shows_edit_reason_types(): void
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);

        $response = $this->actingAs($kassenwart)->get('/kassenbuch');

        $response->assertOk();
        $response->assertViewHas('editReasonTypes');
        $this->assertCount(6, $response->viewData('editReasonTypes'));
    }

    // ==================== Model Tests ====================

    public function test_edit_request_relations(): void
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $vorstand = $this->createUserWithRole(Role::Vorstand);
        $entry = $this->createKassenbuchEntry($kassenwart);

        $editRequest = KassenbuchEditRequest::create([
            'kassenbuch_entry_id' => $entry->id,
            'requested_by' => $kassenwart->id,
            'processed_by' => $vorstand->id,
            'reason_type' => KassenbuchEditReasonType::Tippfehler->value,
            'status' => KassenbuchEditRequest::STATUS_APPROVED,
            'processed_at' => now(),
        ]);

        $this->assertEquals($entry->id, $editRequest->entry->id);
        $this->assertEquals($kassenwart->id, $editRequest->requester->id);
        $this->assertEquals($vorstand->id, $editRequest->processor->id);
    }

    public function test_edit_request_status_helpers(): void
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $entry = $this->createKassenbuchEntry($kassenwart);

        $pendingRequest = KassenbuchEditRequest::create([
            'kassenbuch_entry_id' => $entry->id,
            'requested_by' => $kassenwart->id,
            'reason_type' => KassenbuchEditReasonType::Tippfehler->value,
            'status' => KassenbuchEditRequest::STATUS_PENDING,
        ]);

        $this->assertTrue($pendingRequest->isPending());
        $this->assertFalse($pendingRequest->isApproved());
        $this->assertFalse($pendingRequest->isRejected());

        $pendingRequest->update(['status' => KassenbuchEditRequest::STATUS_APPROVED]);
        $pendingRequest->refresh();

        $this->assertFalse($pendingRequest->isPending());
        $this->assertTrue($pendingRequest->isApproved());
        $this->assertFalse($pendingRequest->isRejected());
    }

    public function test_get_formatted_reason(): void
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $entry = $this->createKassenbuchEntry($kassenwart);

        $request = KassenbuchEditRequest::create([
            'kassenbuch_entry_id' => $entry->id,
            'requested_by' => $kassenwart->id,
            'reason_type' => KassenbuchEditReasonType::FalscherBetrag->value,
            'reason_text' => 'War eigentlich 100€',
            'status' => KassenbuchEditRequest::STATUS_PENDING,
        ]);

        $this->assertEquals('Falscher Betrag: War eigentlich 100€', $request->getFormattedReason());

        $request->update(['reason_text' => null]);
        $request->refresh();

        $this->assertEquals('Falscher Betrag', $request->getFormattedReason());
    }

    public function test_entry_has_pending_and_approved_helpers(): void
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $entry = $this->createKassenbuchEntry($kassenwart);

        $this->assertFalse($entry->hasPendingEditRequest());
        $this->assertFalse($entry->hasApprovedEditRequest());
        $this->assertFalse($entry->canBeEdited());

        KassenbuchEditRequest::create([
            'kassenbuch_entry_id' => $entry->id,
            'requested_by' => $kassenwart->id,
            'reason_type' => KassenbuchEditReasonType::Tippfehler->value,
            'status' => KassenbuchEditRequest::STATUS_PENDING,
        ]);

        // Clear cached relations
        $entry->refresh();

        $this->assertTrue($entry->hasPendingEditRequest());
        $this->assertFalse($entry->hasApprovedEditRequest());
        $this->assertFalse($entry->canBeEdited());
    }

    public function test_entry_was_edited_helper(): void
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $entry = $this->createKassenbuchEntry($kassenwart);

        $this->assertFalse($entry->wasEdited());

        $entry->update([
            'last_edited_by' => $kassenwart->id,
            'last_edited_at' => now(),
            'last_edit_reason' => 'Test',
        ]);

        $this->assertTrue($entry->wasEdited());
    }

    // === Team-Scope Security Tests ===

    public function test_cannot_request_edit_for_entry_from_other_team(): void
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);

        // Create entry in a different team
        $otherTeam = Team::factory()->create();
        $otherEntry = KassenbuchEntry::factory()->create([
            'team_id' => $otherTeam->id,
        ]);

        $response = $this->actingAs($kassenwart)
            ->post("/kassenbuch/eintrag/{$otherEntry->id}/bearbeitung-anfragen", [
                'reason_type' => KassenbuchEditReasonType::Tippfehler->value,
            ]);

        $response->assertNotFound();
    }

    public function test_cannot_approve_request_from_other_team(): void
    {
        $vorstand = $this->createUserWithRole(Role::Vorstand);

        // Create request in a different team
        $otherTeam = Team::factory()->create();
        $otherEntry = KassenbuchEntry::factory()->create([
            'team_id' => $otherTeam->id,
        ]);
        $otherRequest = KassenbuchEditRequest::factory()->pending()->create([
            'kassenbuch_entry_id' => $otherEntry->id,
        ]);

        $response = $this->actingAs($vorstand)
            ->post("/kassenbuch/anfrage/{$otherRequest->id}/freigeben");

        $response->assertNotFound();
    }

    public function test_cannot_reject_request_from_other_team(): void
    {
        $vorstand = $this->createUserWithRole(Role::Vorstand);

        // Create request in a different team
        $otherTeam = Team::factory()->create();
        $otherEntry = KassenbuchEntry::factory()->create([
            'team_id' => $otherTeam->id,
        ]);
        $otherRequest = KassenbuchEditRequest::factory()->pending()->create([
            'kassenbuch_entry_id' => $otherEntry->id,
        ]);

        $response = $this->actingAs($vorstand)
            ->post("/kassenbuch/anfrage/{$otherRequest->id}/ablehnen");

        $response->assertNotFound();
    }

    public function test_cannot_update_entry_from_other_team(): void
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $vorstand = $this->createUserWithRole(Role::Vorstand);

        // Create entry in a different team
        $otherTeam = Team::factory()->create();
        $otherEntry = KassenbuchEntry::factory()->create([
            'team_id' => $otherTeam->id,
        ]);
        KassenbuchEditRequest::factory()->approved()->create([
            'kassenbuch_entry_id' => $otherEntry->id,
            'requested_by' => $kassenwart->id,
            'processed_by' => $vorstand->id,
        ]);

        $response = $this->actingAs($kassenwart)
            ->put("/kassenbuch/eintrag/{$otherEntry->id}", [
                'buchungsdatum' => now()->format('Y-m-d'),
                'betrag' => 100.00,
                'beschreibung' => 'Test',
                'typ' => KassenbuchEntryType::Einnahme->value,
            ]);

        $response->assertNotFound();
    }

    // === Status-Check Security Tests ===

    public function test_cannot_approve_already_approved_request(): void
    {
        $vorstand = $this->createUserWithRole(Role::Vorstand);
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $entry = $this->createKassenbuchEntry($kassenwart);

        $editRequest = KassenbuchEditRequest::create([
            'kassenbuch_entry_id' => $entry->id,
            'requested_by' => $kassenwart->id,
            'processed_by' => $vorstand->id,
            'reason_type' => KassenbuchEditReasonType::Tippfehler->value,
            'status' => KassenbuchEditRequest::STATUS_APPROVED,
            'processed_at' => now(),
        ]);

        $response = $this->actingAs($vorstand)
            ->post("/kassenbuch/anfrage/{$editRequest->id}/freigeben");

        $response->assertRedirect();
        $response->assertSessionHasErrors('request');
    }

    public function test_cannot_approve_already_rejected_request(): void
    {
        $vorstand = $this->createUserWithRole(Role::Vorstand);
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $entry = $this->createKassenbuchEntry($kassenwart);

        $editRequest = KassenbuchEditRequest::create([
            'kassenbuch_entry_id' => $entry->id,
            'requested_by' => $kassenwart->id,
            'processed_by' => $vorstand->id,
            'reason_type' => KassenbuchEditReasonType::Tippfehler->value,
            'status' => KassenbuchEditRequest::STATUS_REJECTED,
            'processed_at' => now(),
        ]);

        $response = $this->actingAs($vorstand)
            ->post("/kassenbuch/anfrage/{$editRequest->id}/freigeben");

        $response->assertRedirect();
        $response->assertSessionHasErrors('request');
    }

    public function test_cannot_reject_already_approved_request(): void
    {
        $vorstand = $this->createUserWithRole(Role::Vorstand);
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $entry = $this->createKassenbuchEntry($kassenwart);

        $editRequest = KassenbuchEditRequest::create([
            'kassenbuch_entry_id' => $entry->id,
            'requested_by' => $kassenwart->id,
            'processed_by' => $vorstand->id,
            'reason_type' => KassenbuchEditReasonType::Tippfehler->value,
            'status' => KassenbuchEditRequest::STATUS_APPROVED,
            'processed_at' => now(),
        ]);

        $response = $this->actingAs($vorstand)
            ->post("/kassenbuch/anfrage/{$editRequest->id}/ablehnen");

        $response->assertRedirect();
        $response->assertSessionHasErrors('request');
    }

    public function test_cannot_reject_already_rejected_request(): void
    {
        $vorstand = $this->createUserWithRole(Role::Vorstand);
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $entry = $this->createKassenbuchEntry($kassenwart);

        $editRequest = KassenbuchEditRequest::create([
            'kassenbuch_entry_id' => $entry->id,
            'requested_by' => $kassenwart->id,
            'processed_by' => $vorstand->id,
            'reason_type' => KassenbuchEditReasonType::Tippfehler->value,
            'status' => KassenbuchEditRequest::STATUS_REJECTED,
            'processed_at' => now(),
        ]);

        $response = $this->actingAs($vorstand)
            ->post("/kassenbuch/anfrage/{$editRequest->id}/ablehnen");

        $response->assertRedirect();
        $response->assertSessionHasErrors('request');
    }

    // === N+1 Query Prevention Test ===

    public function test_helper_methods_use_eager_loaded_relations(): void
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $entry = $this->createKassenbuchEntry($kassenwart);

        KassenbuchEditRequest::create([
            'kassenbuch_entry_id' => $entry->id,
            'requested_by' => $kassenwart->id,
            'reason_type' => KassenbuchEditReasonType::Tippfehler->value,
            'status' => KassenbuchEditRequest::STATUS_PENDING,
        ]);

        // Load entry with eager-loaded relations
        $entryWithRelations = KassenbuchEntry::with(['pendingEditRequest', 'approvedEditRequest'])
            ->find($entry->id);

        // Check that relations are loaded
        $this->assertTrue($entryWithRelations->relationLoaded('pendingEditRequest'));
        $this->assertTrue($entryWithRelations->relationLoaded('approvedEditRequest'));

        // Helper should use loaded relations without extra queries
        DB::enableQueryLog();

        $this->assertTrue($entryWithRelations->hasPendingEditRequest());
        $this->assertFalse($entryWithRelations->hasApprovedEditRequest());

        // No additional queries should be executed
        $this->assertCount(0, DB::getQueryLog());

        DB::disableQueryLog();
    }
}
