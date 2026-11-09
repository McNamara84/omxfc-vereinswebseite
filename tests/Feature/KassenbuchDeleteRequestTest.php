<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\KassenbuchEditReasonType;
use App\Enums\KassenbuchEditRequestType;
use App\Enums\KassenbuchEntryType;
use App\Enums\Role;
use App\Mail\KassenbuchDeleteApproved;
use App\Mail\KassenbuchDeleteRejected;
use App\Mail\KassenbuchDeleteRequestSubmitted;
use App\Models\KassenbuchEditRequest;
use App\Models\KassenbuchEntry;
use App\Models\Kassenstand;
use App\Models\Team;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(KassenbuchDeleteRequestTestFixtures::class);

beforeEach(function (): void {
    $this->team = Team::membersTeam();
    Kassenstand::create(['team_id' => $this->team->id, 'betrag' => 0.0, 'letzte_aktualisierung' => now()]);
});

test('kassenwart can request delete and vorstand gets mail', function (): void {
    Mail::fake();
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $vorstandA = $this->createUserWithRole(Role::Vorstand);
    $vorstandB = $this->createUserWithRole(Role::Vorstand);
    $entry = $this->createKassenbuchEntry($kassenwart);
    $response = $this->actingAs($kassenwart)->post("/kassenbuch/eintrag/{$entry->id}/loeschung-anfragen", ['reason_text' => 'Eintrag wurde doppelt angelegt.']);
    $response->assertRedirect();
    $response->assertSessionHas('status', 'Löschanfrage wurde gestellt.');
    $this->assertDatabaseHas('kassenbuch_edit_requests', ['kassenbuch_entry_id' => $entry->id, 'requested_by' => $kassenwart->id, 'reason_type' => KassenbuchEditReasonType::Sonstiges->value, 'reason_text' => 'Eintrag wurde doppelt angelegt.', 'request_type' => KassenbuchEditRequestType::Delete->value, 'status' => KassenbuchEditRequest::STATUS_PENDING]);
    Mail::assertQueued(KassenbuchDeleteRequestSubmitted::class, fn ($mail) => $mail->hasTo($vorstandA->email) && $mail->hasTo($vorstandB->email) && ! $mail->hasTo($kassenwart->email));
});

test('delete request requires non blank reason text', function (): void {
    Mail::fake();
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $entry = $this->createKassenbuchEntry($kassenwart);
    $response = $this->actingAs($kassenwart)->post("/kassenbuch/eintrag/{$entry->id}/loeschung-anfragen", ['reason_text' => '   ']);
    $response->assertRedirect();
    $response->assertSessionHasErrors('reason_text');
    Mail::assertNothingQueued();
});

test('cannot request delete when pending request exists and error is visible', function (): void {
    Mail::fake();
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $entry = $this->createKassenbuchEntry($kassenwart);
    KassenbuchEditRequest::create(['kassenbuch_entry_id' => $entry->id, 'requested_by' => $kassenwart->id, 'reason_type' => KassenbuchEditReasonType::Sonstiges->value, 'reason_text' => 'Bereits angefragt', 'request_type' => KassenbuchEditRequestType::Delete->value, 'status' => KassenbuchEditRequest::STATUS_PENDING]);
    $response = $this->actingAs($kassenwart)->from('/kassenbuch')->followingRedirects()->post("/kassenbuch/eintrag/{$entry->id}/loeschung-anfragen", ['reason_text' => 'Noch einmal löschen']);
    $response->assertOk();
    $response->assertSee('Für diesen Eintrag existiert bereits eine offene oder freigegebene Anfrage.');
    Mail::assertNothingQueued();
});

test('vorstand cannot request delete', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $vorstand = $this->createUserWithRole(Role::Vorstand);
    $entry = $this->createKassenbuchEntry($kassenwart);
    $response = $this->actingAs($vorstand)->post("/kassenbuch/eintrag/{$entry->id}/loeschung-anfragen", ['reason_text' => 'Soll gelöscht werden']);
    $response->assertForbidden();
});

test('cannot request delete for entry from other team', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $otherTeam = Team::factory()->create();
    $otherEntry = KassenbuchEntry::factory()->create(['team_id' => $otherTeam->id]);
    $response = $this->actingAs($kassenwart)->post("/kassenbuch/eintrag/{$otherEntry->id}/loeschung-anfragen", ['reason_text' => 'Falscher Datensatz']);
    $response->assertNotFound();
});

test('vorstand can approve delete request and soft delete entry', function (): void {
    Mail::fake();
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $vorstandA = $this->createUserWithRole(Role::Vorstand);
    $vorstandB = $this->createUserWithRole(Role::Vorstand);
    Kassenstand::where('team_id', $this->team->id)->update(['betrag' => 150.0]);
    $entry = $this->createKassenbuchEntry($kassenwart, 50.0);
    $deleteRequest = KassenbuchEditRequest::create(['kassenbuch_entry_id' => $entry->id, 'requested_by' => $kassenwart->id, 'reason_type' => KassenbuchEditReasonType::Sonstiges->value, 'reason_text' => 'Eintrag wurde doppelt angelegt.', 'request_type' => KassenbuchEditRequestType::Delete->value, 'status' => KassenbuchEditRequest::STATUS_PENDING]);
    $response = $this->actingAs($vorstandA)->post("/kassenbuch/anfrage/{$deleteRequest->id}/freigeben");
    $response->assertRedirect();
    $response->assertSessionHas('status', 'Löschung wurde freigegeben und durchgeführt.');
    $this->assertSoftDeleted('kassenbuch_entries', ['id' => $entry->id]);
    $deleteRequest->refresh();
    expect($deleteRequest->status)->toBe(KassenbuchEditRequest::STATUS_APPROVED)
        ->and($deleteRequest->processed_by)->toBe($vorstandA->id)
        ->and((float) Kassenstand::where('team_id', $this->team->id)->value('betrag'))->toBe(100.0);
    Mail::assertQueued(KassenbuchDeleteApproved::class, fn ($mail) => $mail->hasTo($kassenwart->email) && ! $mail->hasTo($vorstandA->email) && ! $mail->hasTo($vorstandB->email) && $mail->hasBcc($vorstandA->email) && $mail->hasBcc($vorstandB->email));
});

test('vorstand can approve delete request without existing kassenstand row', function (): void {
    Mail::fake();
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $vorstand = $this->createUserWithRole(Role::Vorstand);
    Kassenstand::where('team_id', $this->team->id)->delete();
    $deletedEntry = $this->createKassenbuchEntry($kassenwart, 50.0);
    $remainingEntry = $this->createKassenbuchEntry($kassenwart, 25.0);
    $deleteRequest = KassenbuchEditRequest::create(['kassenbuch_entry_id' => $deletedEntry->id, 'requested_by' => $kassenwart->id, 'reason_type' => KassenbuchEditReasonType::Sonstiges->value, 'reason_text' => 'Eintrag wurde doppelt angelegt.', 'request_type' => KassenbuchEditRequestType::Delete->value, 'status' => KassenbuchEditRequest::STATUS_PENDING]);
    $response = $this->actingAs($vorstand)->post("/kassenbuch/anfrage/{$deleteRequest->id}/freigeben");
    $response->assertRedirect();
    $response->assertSessionHas('status', 'Löschung wurde freigegeben und durchgeführt.');
    $this->assertSoftDeleted('kassenbuch_entries', ['id' => $deletedEntry->id]);
    $this->assertDatabaseHas('kassenbuch_entries', ['id' => $remainingEntry->id]);
    $this->assertDatabaseHas('kassenstand', ['team_id' => $this->team->id, 'betrag' => 25.0]);
});

test('delete approval reverses ausgabe in kassenstand', function (): void {
    Mail::fake();
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $vorstand = $this->createUserWithRole(Role::Vorstand);
    Kassenstand::where('team_id', $this->team->id)->update(['betrag' => 30.0]);
    $entry = $this->createKassenbuchEntry($kassenwart, -20.0);
    $deleteRequest = KassenbuchEditRequest::create(['kassenbuch_entry_id' => $entry->id, 'requested_by' => $kassenwart->id, 'reason_type' => KassenbuchEditReasonType::Sonstiges->value, 'reason_text' => 'Falsche Ausgabe', 'request_type' => KassenbuchEditRequestType::Delete->value, 'status' => KassenbuchEditRequest::STATUS_PENDING]);
    $this->actingAs($vorstand)->post("/kassenbuch/anfrage/{$deleteRequest->id}/freigeben")->assertRedirect();
    expect((float) Kassenstand::where('team_id', $this->team->id)->value('betrag'))->toBe(50.0);
});

test('vorstand can reject delete request and mail requester', function (): void {
    Mail::fake();
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $vorstand = $this->createUserWithRole(Role::Vorstand);
    $entry = $this->createKassenbuchEntry($kassenwart);
    $deleteRequest = KassenbuchEditRequest::create(['kassenbuch_entry_id' => $entry->id, 'requested_by' => $kassenwart->id, 'reason_type' => KassenbuchEditReasonType::Sonstiges->value, 'reason_text' => 'Eintrag wurde doppelt angelegt.', 'request_type' => KassenbuchEditRequestType::Delete->value, 'status' => KassenbuchEditRequest::STATUS_PENDING]);
    $response = $this->actingAs($vorstand)->post("/kassenbuch/anfrage/{$deleteRequest->id}/ablehnen", ['rejection_reason' => 'Bitte stattdessen korrigieren.']);
    $response->assertRedirect();
    $response->assertSessionHas('status', 'Löschanfrage wurde abgelehnt.');
    $deleteRequest->refresh();
    expect($deleteRequest->status)->toBe(KassenbuchEditRequest::STATUS_REJECTED)
        ->and($deleteRequest->rejection_reason)->toBe('Bitte stattdessen korrigieren.');
    $this->assertDatabaseHas('kassenbuch_entries', ['id' => $entry->id]);
    Mail::assertQueued(KassenbuchDeleteRejected::class, fn ($mail) => $mail->hasTo($kassenwart->email) && ! $mail->hasTo($vorstand->email));
});

test('soft deleted entries are hidden from kassenbuch index', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $visibleEntry = $this->createKassenbuchEntry($kassenwart, 25.0);
    $deletedEntry = KassenbuchEntry::create(['team_id' => $this->team->id, 'created_by' => $kassenwart->id, 'buchungsdatum' => now(), 'betrag' => 10.0, 'beschreibung' => 'Versteckter Eintrag', 'typ' => KassenbuchEntryType::Einnahme->value]);
    $deletedEntry->delete();
    $response = $this->actingAs($kassenwart)->get('/kassenbuch');
    $response->assertOk();
    $response->assertSee($visibleEntry->beschreibung);
    $response->assertDontSee('Versteckter Eintrag');
});

test('kassenwart sees pending delete badge in kassenbuch index', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $entry = $this->createKassenbuchEntry($kassenwart);
    KassenbuchEditRequest::create(['kassenbuch_entry_id' => $entry->id, 'requested_by' => $kassenwart->id, 'reason_type' => KassenbuchEditReasonType::Sonstiges->value, 'reason_text' => 'Eintrag wurde doppelt angelegt.', 'request_type' => KassenbuchEditRequestType::Delete->value, 'status' => KassenbuchEditRequest::STATUS_PENDING]);
    $response = $this->actingAs($kassenwart)->get('/kassenbuch');
    $response->assertOk();
    $response->assertSee('Löschanfrage läuft');
    $response->assertDontSee('Löschen anfragen');
});

test('vorstand sees delete request in pending requests panel', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $vorstand = $this->createUserWithRole(Role::Vorstand);
    $entry = $this->createKassenbuchEntry($kassenwart);
    KassenbuchEditRequest::create(['kassenbuch_entry_id' => $entry->id, 'requested_by' => $kassenwart->id, 'reason_type' => KassenbuchEditReasonType::Sonstiges->value, 'reason_text' => 'Eintrag wurde doppelt angelegt.', 'request_type' => KassenbuchEditRequestType::Delete->value, 'status' => KassenbuchEditRequest::STATUS_PENDING]);
    $response = $this->actingAs($vorstand)->get('/kassenbuch');
    $response->assertOk();
    $response->assertSee('Offene Freigabeanfragen (1)');
    $response->assertSee('Löschung');
    $response->assertSee('Eintrag wurde doppelt angelegt.');
    $response->assertSee('Anfrage ablehnen');
    $response->assertSee('Angefragter Vorgang:');
    $response->assertDontSee('Bearbeitungsanfrage ablehnen');
});
