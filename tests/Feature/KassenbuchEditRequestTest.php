<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\KassenbuchEditReasonType;
use App\Enums\KassenbuchEditRequestType;
use App\Enums\KassenbuchEntryType;
use App\Enums\Role;
use App\Models\KassenbuchEditRequest;
use App\Models\KassenbuchEntry;
use App\Models\Kassenstand;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(KassenbuchEditRequestTestFixtures::class);

covers(KassenbuchEditRequest::class);

beforeEach(function (): void {
    $this->team = Team::membersTeam();
    // Create initial kassenstand
    Kassenstand::create(['team_id' => $this->team->id, 'betrag' => 100.0, 'letzte_aktualisierung' => now()]);
});

test('kassenwart can request edit', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $entry = $this->createKassenbuchEntry($kassenwart);
    $response = $this->actingAs($kassenwart)->post("/kassenbuch/eintrag/{$entry->id}/bearbeitung-anfragen", ['reason_type' => KassenbuchEditReasonType::Tippfehler->value, 'reason_text' => null]);
    $response->assertRedirect();
    $response->assertSessionHas('status', 'Bearbeitungsanfrage wurde gestellt.');
    $this->assertDatabaseHas('kassenbuch_edit_requests', ['kassenbuch_entry_id' => $entry->id, 'requested_by' => $kassenwart->id, 'reason_type' => 'tippfehler', 'status' => 'pending']);
});

test('admin can request edit', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $entry = $this->createKassenbuchEntry($admin);
    $response = $this->actingAs($admin)->post("/kassenbuch/eintrag/{$entry->id}/bearbeitung-anfragen", ['reason_type' => KassenbuchEditReasonType::FalscherBetrag->value]);
    $response->assertRedirect();
    $this->assertDatabaseHas('kassenbuch_edit_requests', ['kassenbuch_entry_id' => $entry->id, 'status' => 'pending']);
});

test('mitglied cannot request edit', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $mitglied = $this->createUserWithRole(Role::Mitglied);
    $entry = $this->createKassenbuchEntry($kassenwart);
    $response = $this->actingAs($mitglied)->post("/kassenbuch/eintrag/{$entry->id}/bearbeitung-anfragen", ['reason_type' => KassenbuchEditReasonType::Tippfehler->value]);
    $response->assertForbidden();
});

test('vorstand cannot request edit', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $vorstand = $this->createUserWithRole(Role::Vorstand);
    $entry = $this->createKassenbuchEntry($kassenwart);
    $response = $this->actingAs($vorstand)->post("/kassenbuch/eintrag/{$entry->id}/bearbeitung-anfragen", ['reason_type' => KassenbuchEditReasonType::Tippfehler->value]);
    $response->assertForbidden();
});

test('cannot request edit when pending exists', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $entry = $this->createKassenbuchEntry($kassenwart);
    // First request
    KassenbuchEditRequest::create(['kassenbuch_entry_id' => $entry->id, 'requested_by' => $kassenwart->id, 'reason_type' => KassenbuchEditReasonType::Tippfehler->value, 'status' => KassenbuchEditRequest::STATUS_PENDING]);
    // Second request should fail with error
    $response = $this->actingAs($kassenwart)->post("/kassenbuch/eintrag/{$entry->id}/bearbeitung-anfragen", ['reason_type' => KassenbuchEditReasonType::FalscherBetrag->value]);
    $response->assertRedirect();
    $response->assertSessionHas('error', 'Für diesen Eintrag existiert bereits eine offene oder freigegebene Anfrage.');
});

test('cannot request edit when approved exists', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $vorstand = $this->createUserWithRole(Role::Vorstand);
    $entry = $this->createKassenbuchEntry($kassenwart);
    // Create approved request
    KassenbuchEditRequest::create(['kassenbuch_entry_id' => $entry->id, 'requested_by' => $kassenwart->id, 'processed_by' => $vorstand->id, 'reason_type' => KassenbuchEditReasonType::Tippfehler->value, 'status' => KassenbuchEditRequest::STATUS_APPROVED, 'processed_at' => now()]);
    // New request should fail
    $response = $this->actingAs($kassenwart)->post("/kassenbuch/eintrag/{$entry->id}/bearbeitung-anfragen", ['reason_type' => KassenbuchEditReasonType::FalscherBetrag->value]);
    $response->assertRedirect();
    $response->assertSessionHas('error', 'Für diesen Eintrag existiert bereits eine offene oder freigegebene Anfrage.');
});

test('sonstiges requires reason text', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $entry = $this->createKassenbuchEntry($kassenwart);
    $response = $this->actingAs($kassenwart)->post("/kassenbuch/eintrag/{$entry->id}/bearbeitung-anfragen", ['reason_type' => KassenbuchEditReasonType::Sonstiges->value, 'reason_text' => '']);
    $response->assertRedirect();
    $response->assertSessionHasErrors('reason_text');
});

test('sonstiges rejects whitespace only reason text', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $entry = $this->createKassenbuchEntry($kassenwart);
    $response = $this->actingAs($kassenwart)->post("/kassenbuch/eintrag/{$entry->id}/bearbeitung-anfragen", ['reason_type' => KassenbuchEditReasonType::Sonstiges->value, 'reason_text' => '   ']);
    $response->assertRedirect();
    $response->assertSessionHasErrors('reason_text');
});

test('sonstiges with reason text succeeds', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $entry = $this->createKassenbuchEntry($kassenwart);
    $response = $this->actingAs($kassenwart)->post("/kassenbuch/eintrag/{$entry->id}/bearbeitung-anfragen", ['reason_type' => KassenbuchEditReasonType::Sonstiges->value, 'reason_text' => 'Spezielle Korrektur erforderlich']);
    $response->assertRedirect();
    $response->assertSessionHas('status');
    $this->assertDatabaseHas('kassenbuch_edit_requests', ['kassenbuch_entry_id' => $entry->id, 'reason_type' => 'sonstiges', 'reason_text' => 'Spezielle Korrektur erforderlich']);
});

test('vorstand can approve edit request', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $vorstand = $this->createUserWithRole(Role::Vorstand);
    $entry = $this->createKassenbuchEntry($kassenwart);
    $editRequest = KassenbuchEditRequest::create(['kassenbuch_entry_id' => $entry->id, 'requested_by' => $kassenwart->id, 'reason_type' => KassenbuchEditReasonType::Tippfehler->value, 'status' => KassenbuchEditRequest::STATUS_PENDING]);
    $response = $this->actingAs($vorstand)->post("/kassenbuch/anfrage/{$editRequest->id}/freigeben");
    $response->assertRedirect();
    $response->assertSessionHas('status', 'Bearbeitung wurde freigegeben.');
    $editRequest->refresh();
    expect($editRequest->status)->toEqual(KassenbuchEditRequest::STATUS_APPROVED)
        ->and($editRequest->processed_by)->toEqual($vorstand->id)
        ->and($editRequest->processed_at)->not->toBeNull();
});

test('admin can approve edit request', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $admin = $this->createUserWithRole(Role::Admin);
    $entry = $this->createKassenbuchEntry($kassenwart);
    $editRequest = KassenbuchEditRequest::create(['kassenbuch_entry_id' => $entry->id, 'requested_by' => $kassenwart->id, 'reason_type' => KassenbuchEditReasonType::Tippfehler->value, 'status' => KassenbuchEditRequest::STATUS_PENDING]);
    $response = $this->actingAs($admin)->post("/kassenbuch/anfrage/{$editRequest->id}/freigeben");
    $response->assertRedirect();
    $editRequest->refresh();
    expect($editRequest->status)->toEqual(KassenbuchEditRequest::STATUS_APPROVED);
});

test('kassenwart cannot approve edit request', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $entry = $this->createKassenbuchEntry($kassenwart);
    $editRequest = KassenbuchEditRequest::create(['kassenbuch_entry_id' => $entry->id, 'requested_by' => $kassenwart->id, 'reason_type' => KassenbuchEditReasonType::Tippfehler->value, 'status' => KassenbuchEditRequest::STATUS_PENDING]);
    $response = $this->actingAs($kassenwart)->post("/kassenbuch/anfrage/{$editRequest->id}/freigeben");
    $response->assertForbidden();
});

test('vorstand can reject edit request with reason', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $vorstand = $this->createUserWithRole(Role::Vorstand);
    $entry = $this->createKassenbuchEntry($kassenwart);
    $editRequest = KassenbuchEditRequest::create(['kassenbuch_entry_id' => $entry->id, 'requested_by' => $kassenwart->id, 'reason_type' => KassenbuchEditReasonType::Tippfehler->value, 'status' => KassenbuchEditRequest::STATUS_PENDING]);
    $response = $this->actingAs($vorstand)->post("/kassenbuch/anfrage/{$editRequest->id}/ablehnen", ['rejection_reason' => 'Eintrag ist korrekt']);
    $response->assertRedirect();
    $response->assertSessionHas('status', 'Bearbeitungsanfrage wurde abgelehnt.');
    $editRequest->refresh();
    expect($editRequest->status)->toEqual(KassenbuchEditRequest::STATUS_REJECTED)
        ->and($editRequest->rejection_reason)->toEqual('Eintrag ist korrekt')
        ->and($editRequest->processed_by)->toEqual($vorstand->id);
});

test('vorstand can reject without reason', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $vorstand = $this->createUserWithRole(Role::Vorstand);
    $entry = $this->createKassenbuchEntry($kassenwart);
    $editRequest = KassenbuchEditRequest::create(['kassenbuch_entry_id' => $entry->id, 'requested_by' => $kassenwart->id, 'reason_type' => KassenbuchEditReasonType::Tippfehler->value, 'status' => KassenbuchEditRequest::STATUS_PENDING]);
    $response = $this->actingAs($vorstand)->post("/kassenbuch/anfrage/{$editRequest->id}/ablehnen");
    $response->assertRedirect();
    $editRequest->refresh();
    expect($editRequest->status)->toEqual(KassenbuchEditRequest::STATUS_REJECTED)
        ->and($editRequest->rejection_reason)->toBeNull();
});

test('kassenwart can edit after approval', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $vorstand = $this->createUserWithRole(Role::Vorstand);
    $entry = $this->createKassenbuchEntry($kassenwart, 50.0);
    // Create approved request
    KassenbuchEditRequest::create(['kassenbuch_entry_id' => $entry->id, 'requested_by' => $kassenwart->id, 'processed_by' => $vorstand->id, 'reason_type' => KassenbuchEditReasonType::FalscherBetrag->value, 'reason_text' => 'Betrag war 75€', 'status' => KassenbuchEditRequest::STATUS_APPROVED, 'processed_at' => now()]);
    $response = $this->actingAs($kassenwart)->put("/kassenbuch/eintrag/{$entry->id}", ['buchungsdatum' => '2025-01-15', 'betrag' => 75.0, 'beschreibung' => 'Korrigierter Test-Eintrag', 'typ' => KassenbuchEntryType::Einnahme->value]);
    $response->assertRedirect();
    $response->assertSessionHas('status', 'Kassenbucheintrag wurde aktualisiert.');
    $entry->refresh();
    expect($entry->betrag)->toEqual(75.0)
        ->and($entry->beschreibung)->toEqual('Korrigierter Test-Eintrag')
        ->and($entry->last_edited_by)->toEqual($kassenwart->id)
        ->and($entry->last_edited_at)->not->toBeNull()
        ->and($entry->last_edit_reason)->toContain('Falscher Betrag');
    // Edit request should be deleted
    $this->assertDatabaseMissing('kassenbuch_edit_requests', ['kassenbuch_entry_id' => $entry->id]);
});

test('kassenwart cannot edit without approval', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $entry = $this->createKassenbuchEntry($kassenwart);
    $response = $this->actingAs($kassenwart)->put("/kassenbuch/eintrag/{$entry->id}", ['buchungsdatum' => '2025-01-15', 'betrag' => 75.0, 'beschreibung' => 'Korrigierter Test-Eintrag', 'typ' => KassenbuchEntryType::Einnahme->value]);
    $response->assertForbidden();
});

test('kassenwart cannot edit with pending request', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $entry = $this->createKassenbuchEntry($kassenwart);
    // Create pending request (not approved)
    KassenbuchEditRequest::create(['kassenbuch_entry_id' => $entry->id, 'requested_by' => $kassenwart->id, 'reason_type' => KassenbuchEditReasonType::Tippfehler->value, 'status' => KassenbuchEditRequest::STATUS_PENDING]);
    $response = $this->actingAs($kassenwart)->put("/kassenbuch/eintrag/{$entry->id}", ['buchungsdatum' => '2025-01-15', 'betrag' => 75.0, 'beschreibung' => 'Korrigierter Test-Eintrag', 'typ' => KassenbuchEntryType::Einnahme->value]);
    $response->assertForbidden();
});

test('edit updates kassenstand correctly', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $vorstand = $this->createUserWithRole(Role::Vorstand);
    // Setze Kassenstand auf 100
    Kassenstand::where('team_id', $this->team->id)->update(['betrag' => 100.0]);
    // Erstelle Eintrag mit 50€ Einnahme
    $entry = $this->createKassenbuchEntry($kassenwart, 50.0);
    KassenbuchEditRequest::create(['kassenbuch_entry_id' => $entry->id, 'requested_by' => $kassenwart->id, 'processed_by' => $vorstand->id, 'reason_type' => KassenbuchEditReasonType::FalscherBetrag->value, 'status' => KassenbuchEditRequest::STATUS_APPROVED, 'processed_at' => now()]);
    // Ändere von 50 auf 75 (Differenz von +25)
    $this->actingAs($kassenwart)->put("/kassenbuch/eintrag/{$entry->id}", ['buchungsdatum' => now()->format('Y-m-d'), 'betrag' => 75.0, 'beschreibung' => 'Korrigiert', 'typ' => KassenbuchEntryType::Einnahme->value]);
    $kassenstand = Kassenstand::where('team_id', $this->team->id)->first();
    // 100 - 50 (alt) + 75 (neu) = 125
    expect($kassenstand->betrag)->toEqual(125.0);
});

test('edit from einnahme to ausgabe updates kassenstand', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $vorstand = $this->createUserWithRole(Role::Vorstand);
    // Setze Kassenstand auf 100
    Kassenstand::where('team_id', $this->team->id)->update(['betrag' => 100.0]);
    // Erstelle Eintrag mit 50€ Einnahme
    $entry = $this->createKassenbuchEntry($kassenwart, 50.0);
    KassenbuchEditRequest::create(['kassenbuch_entry_id' => $entry->id, 'requested_by' => $kassenwart->id, 'processed_by' => $vorstand->id, 'reason_type' => KassenbuchEditReasonType::FalscherTyp->value, 'status' => KassenbuchEditRequest::STATUS_APPROVED, 'processed_at' => now()]);
    // Ändere von Einnahme 50 zu Ausgabe 50
    $this->actingAs($kassenwart)->put("/kassenbuch/eintrag/{$entry->id}", ['buchungsdatum' => now()->format('Y-m-d'), 'betrag' => 50.0, 'beschreibung' => 'Korrigiert', 'typ' => KassenbuchEntryType::Ausgabe->value]);
    $kassenstand = Kassenstand::where('team_id', $this->team->id)->first();
    // 100 - 50 (alte Einnahme) + (-50) (neue Ausgabe) = 0
    expect($kassenstand->betrag)->toEqual(0.0);
    $entry->refresh();
    expect($entry->betrag)->toEqual(-50.0);
});

test('vorstand sees pending edit requests', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $vorstand = $this->createUserWithRole(Role::Vorstand);
    $entry = $this->createKassenbuchEntry($kassenwart);
    KassenbuchEditRequest::create(['kassenbuch_entry_id' => $entry->id, 'requested_by' => $kassenwart->id, 'reason_type' => KassenbuchEditReasonType::Tippfehler->value, 'status' => KassenbuchEditRequest::STATUS_PENDING]);
    $response = $this->actingAs($vorstand)->get('/kassenbuch');
    $response->assertOk();
    $response->assertSee('Offene Freigabeanfragen (1)');
});

test('kassenwart does not see pending requests section', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $entry = $this->createKassenbuchEntry($kassenwart);
    KassenbuchEditRequest::create(['kassenbuch_entry_id' => $entry->id, 'requested_by' => $kassenwart->id, 'reason_type' => KassenbuchEditReasonType::Tippfehler->value, 'status' => KassenbuchEditRequest::STATUS_PENDING]);
    $response = $this->actingAs($kassenwart)->get('/kassenbuch');
    $response->assertOk();
    $response->assertDontSee('Offene Freigabeanfragen');
});

test('index shows edit reason types', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $response = $this->actingAs($kassenwart)->get('/kassenbuch');
    $response->assertOk();
    // Verify all edit reason types are present in the rendered page
    foreach (KassenbuchEditReasonType::cases() as $type) {
        $response->assertSee($type->label());
        $response->assertSee($type->value, false);
    }
});

test('edit request relations', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $vorstand = $this->createUserWithRole(Role::Vorstand);
    $entry = $this->createKassenbuchEntry($kassenwart);
    $editRequest = KassenbuchEditRequest::create(['kassenbuch_entry_id' => $entry->id, 'requested_by' => $kassenwart->id, 'processed_by' => $vorstand->id, 'reason_type' => KassenbuchEditReasonType::Tippfehler->value, 'status' => KassenbuchEditRequest::STATUS_APPROVED, 'processed_at' => now()]);
    expect($editRequest->entry->id)->toEqual($entry->id)
        ->and($editRequest->requester->id)->toEqual($kassenwart->id)
        ->and($editRequest->processor->id)->toEqual($vorstand->id);
});

test('edit request status helpers', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $entry = $this->createKassenbuchEntry($kassenwart);
    $pendingRequest = KassenbuchEditRequest::create(['kassenbuch_entry_id' => $entry->id, 'requested_by' => $kassenwart->id, 'reason_type' => KassenbuchEditReasonType::Tippfehler->value, 'status' => KassenbuchEditRequest::STATUS_PENDING]);
    expect($pendingRequest->isPending())->toBeTrue()
        ->and($pendingRequest->isApproved())->toBeFalse()
        ->and($pendingRequest->isRejected())->toBeFalse();
    $pendingRequest->update(['status' => KassenbuchEditRequest::STATUS_APPROVED]);
    $pendingRequest->refresh();
    expect($pendingRequest->isPending())->toBeFalse()
        ->and($pendingRequest->isApproved())->toBeTrue()
        ->and($pendingRequest->isRejected())->toBeFalse();
});

test('get formatted reason', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $entry = $this->createKassenbuchEntry($kassenwart);
    $request = KassenbuchEditRequest::create(['kassenbuch_entry_id' => $entry->id, 'requested_by' => $kassenwart->id, 'reason_type' => KassenbuchEditReasonType::FalscherBetrag->value, 'reason_text' => 'War eigentlich 100€', 'status' => KassenbuchEditRequest::STATUS_PENDING]);
    expect($request->getFormattedReason())->toEqual('Falscher Betrag: War eigentlich 100€');
    $request->update(['reason_text' => null]);
    $request->refresh();
    expect($request->getFormattedReason())->toEqual('Falscher Betrag');
});

test('delete request helper methods', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $entry = $this->createKassenbuchEntry($kassenwart);
    $request = KassenbuchEditRequest::create(['kassenbuch_entry_id' => $entry->id, 'requested_by' => $kassenwart->id, 'reason_type' => KassenbuchEditReasonType::Sonstiges->value, 'reason_text' => 'Doppelt erstellt', 'request_type' => KassenbuchEditRequestType::Delete->value, 'status' => KassenbuchEditRequest::STATUS_PENDING]);
    expect($request->isDeleteRequest())->toBeTrue()
        ->and($request->isEditRequest())->toBeFalse()
        ->and($request->requestTypeLabel())->toBe('Löschung')
        ->and($request->displayReason())->toBe('Doppelt erstellt');
});

test('entry has pending and approved helpers', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $entry = $this->createKassenbuchEntry($kassenwart);
    expect($entry->hasPendingEditRequest())->toBeFalse()
        ->and($entry->hasApprovedEditRequest())->toBeFalse()
        ->and($entry->canBeEdited())->toBeFalse();
    KassenbuchEditRequest::create(['kassenbuch_entry_id' => $entry->id, 'requested_by' => $kassenwart->id, 'reason_type' => KassenbuchEditReasonType::Tippfehler->value, 'status' => KassenbuchEditRequest::STATUS_PENDING]);
    // Clear cached relations
    $entry->refresh();
    expect($entry->hasPendingEditRequest())->toBeTrue()
        ->and($entry->hasApprovedEditRequest())->toBeFalse()
        ->and($entry->canBeEdited())->toBeFalse();
});

test('entry has pending delete and generic request helpers', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $entry = $this->createKassenbuchEntry($kassenwart);
    expect($entry->hasPendingRequest())->toBeFalse()
        ->and($entry->hasApprovedRequest())->toBeFalse()
        ->and($entry->hasPendingDeleteRequest())->toBeFalse();
    KassenbuchEditRequest::create(['kassenbuch_entry_id' => $entry->id, 'requested_by' => $kassenwart->id, 'reason_type' => KassenbuchEditReasonType::Sonstiges->value, 'reason_text' => 'Doppelt erstellt', 'request_type' => KassenbuchEditRequestType::Delete->value, 'status' => KassenbuchEditRequest::STATUS_PENDING]);
    $entry->refresh();
    expect($entry->hasPendingRequest())->toBeTrue()
        ->and($entry->hasApprovedRequest())->toBeFalse()
        ->and($entry->hasPendingDeleteRequest())->toBeTrue()
        ->and($entry->hasPendingEditRequest())->toBeFalse();
});

test('entry was edited helper', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $entry = $this->createKassenbuchEntry($kassenwart);
    expect($entry->wasEdited())->toBeFalse();
    $entry->update(['last_edited_by' => $kassenwart->id, 'last_edited_at' => now(), 'last_edit_reason' => 'Test']);
    expect($entry->wasEdited())->toBeTrue();
});

test('cannot request edit for entry from other team', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    // Create entry in a different team
    $otherTeam = Team::factory()->create();
    $otherEntry = KassenbuchEntry::factory()->create(['team_id' => $otherTeam->id]);
    $response = $this->actingAs($kassenwart)->post("/kassenbuch/eintrag/{$otherEntry->id}/bearbeitung-anfragen", ['reason_type' => KassenbuchEditReasonType::Tippfehler->value]);
    $response->assertNotFound();
});

test('cannot approve request from other team', function (): void {
    $vorstand = $this->createUserWithRole(Role::Vorstand);
    // Create request in a different team
    $otherTeam = Team::factory()->create();
    $otherEntry = KassenbuchEntry::factory()->create(['team_id' => $otherTeam->id]);
    $otherRequest = KassenbuchEditRequest::factory()->pending()->create(['kassenbuch_entry_id' => $otherEntry->id]);
    $response = $this->actingAs($vorstand)->post("/kassenbuch/anfrage/{$otherRequest->id}/freigeben");
    $response->assertNotFound();
});

test('cannot reject request from other team', function (): void {
    $vorstand = $this->createUserWithRole(Role::Vorstand);
    // Create request in a different team
    $otherTeam = Team::factory()->create();
    $otherEntry = KassenbuchEntry::factory()->create(['team_id' => $otherTeam->id]);
    $otherRequest = KassenbuchEditRequest::factory()->pending()->create(['kassenbuch_entry_id' => $otherEntry->id]);
    $response = $this->actingAs($vorstand)->post("/kassenbuch/anfrage/{$otherRequest->id}/ablehnen");
    $response->assertNotFound();
});

test('cannot update entry from other team', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $vorstand = $this->createUserWithRole(Role::Vorstand);
    // Create entry in a different team
    $otherTeam = Team::factory()->create();
    $otherEntry = KassenbuchEntry::factory()->create(['team_id' => $otherTeam->id]);
    KassenbuchEditRequest::factory()->approved()->create(['kassenbuch_entry_id' => $otherEntry->id, 'requested_by' => $kassenwart->id, 'processed_by' => $vorstand->id]);
    $response = $this->actingAs($kassenwart)->put("/kassenbuch/eintrag/{$otherEntry->id}", ['buchungsdatum' => now()->format('Y-m-d'), 'betrag' => 100.0, 'beschreibung' => 'Test', 'typ' => KassenbuchEntryType::Einnahme->value]);
    $response->assertNotFound();
});

test('cannot approve already approved request', function (): void {
    $vorstand = $this->createUserWithRole(Role::Vorstand);
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $entry = $this->createKassenbuchEntry($kassenwart);
    $editRequest = KassenbuchEditRequest::create(['kassenbuch_entry_id' => $entry->id, 'requested_by' => $kassenwart->id, 'processed_by' => $vorstand->id, 'reason_type' => KassenbuchEditReasonType::Tippfehler->value, 'status' => KassenbuchEditRequest::STATUS_APPROVED, 'processed_at' => now()]);
    $response = $this->actingAs($vorstand)->post("/kassenbuch/anfrage/{$editRequest->id}/freigeben");
    $response->assertRedirect();
    $response->assertSessionHas('error', 'Diese Anfrage wurde bereits bearbeitet.');
});

test('cannot approve already rejected request', function (): void {
    $vorstand = $this->createUserWithRole(Role::Vorstand);
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $entry = $this->createKassenbuchEntry($kassenwart);
    $editRequest = KassenbuchEditRequest::create(['kassenbuch_entry_id' => $entry->id, 'requested_by' => $kassenwart->id, 'processed_by' => $vorstand->id, 'reason_type' => KassenbuchEditReasonType::Tippfehler->value, 'status' => KassenbuchEditRequest::STATUS_REJECTED, 'processed_at' => now()]);
    $response = $this->actingAs($vorstand)->post("/kassenbuch/anfrage/{$editRequest->id}/freigeben");
    $response->assertRedirect();
    $response->assertSessionHas('error', 'Diese Anfrage wurde bereits bearbeitet.');
});

test('cannot reject already approved request', function (): void {
    $vorstand = $this->createUserWithRole(Role::Vorstand);
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $entry = $this->createKassenbuchEntry($kassenwart);
    $editRequest = KassenbuchEditRequest::create(['kassenbuch_entry_id' => $entry->id, 'requested_by' => $kassenwart->id, 'processed_by' => $vorstand->id, 'reason_type' => KassenbuchEditReasonType::Tippfehler->value, 'status' => KassenbuchEditRequest::STATUS_APPROVED, 'processed_at' => now()]);
    $response = $this->actingAs($vorstand)->post("/kassenbuch/anfrage/{$editRequest->id}/ablehnen");
    $response->assertRedirect();
    $response->assertSessionHas('error', 'Diese Anfrage wurde bereits bearbeitet.');
});

test('cannot reject already rejected request', function (): void {
    $vorstand = $this->createUserWithRole(Role::Vorstand);
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $entry = $this->createKassenbuchEntry($kassenwart);
    $editRequest = KassenbuchEditRequest::create(['kassenbuch_entry_id' => $entry->id, 'requested_by' => $kassenwart->id, 'processed_by' => $vorstand->id, 'reason_type' => KassenbuchEditReasonType::Tippfehler->value, 'status' => KassenbuchEditRequest::STATUS_REJECTED, 'processed_at' => now()]);
    $response = $this->actingAs($vorstand)->post("/kassenbuch/anfrage/{$editRequest->id}/ablehnen");
    $response->assertRedirect();
    $response->assertSessionHas('error', 'Diese Anfrage wurde bereits bearbeitet.');
});

test('helper methods use eager loaded relations', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $entry = $this->createKassenbuchEntry($kassenwart);
    KassenbuchEditRequest::create(['kassenbuch_entry_id' => $entry->id, 'requested_by' => $kassenwart->id, 'reason_type' => KassenbuchEditReasonType::Tippfehler->value, 'status' => KassenbuchEditRequest::STATUS_PENDING]);
    // Load entry with eager-loaded relations
    $entryWithRelations = KassenbuchEntry::with(['pendingEditRequest', 'approvedEditRequest'])->find($entry->id);
    // Check that relations are loaded
    expect($entryWithRelations->relationLoaded('pendingEditRequest'))->toBeTrue();
    expect($entryWithRelations->relationLoaded('approvedEditRequest'))->toBeTrue();
    // Helper should use loaded relations without extra queries
    DB::enableQueryLog();
    expect($entryWithRelations->hasPendingEditRequest())->toBeTrue()
        ->and($entryWithRelations->hasApprovedEditRequest())->toBeFalse();
    // No additional queries should be executed
    expect(DB::getQueryLog())->toHaveCount(0);
    DB::disableQueryLog();
});
