<?php

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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class KassenbuchDeleteRequestTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = Team::membersTeam();

        Kassenstand::create([
            'team_id' => $this->team->id,
            'betrag' => 0.00,
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
            'beschreibung' => 'Doppelter Eintrag',
            'typ' => $betrag < 0 ? KassenbuchEntryType::Ausgabe->value : KassenbuchEntryType::Einnahme->value,
        ]);
    }

    public function test_kassenwart_can_request_delete_and_vorstand_gets_mail(): void
    {
        Mail::fake();

        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $vorstandA = $this->createUserWithRole(Role::Vorstand);
        $vorstandB = $this->createUserWithRole(Role::Vorstand);
        $entry = $this->createKassenbuchEntry($kassenwart);

        $response = $this->actingAs($kassenwart)
            ->post("/kassenbuch/eintrag/{$entry->id}/loeschung-anfragen", [
                'reason_text' => 'Eintrag wurde doppelt angelegt.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Löschanfrage wurde gestellt.');

        $this->assertDatabaseHas('kassenbuch_edit_requests', [
            'kassenbuch_entry_id' => $entry->id,
            'requested_by' => $kassenwart->id,
            'reason_type' => KassenbuchEditReasonType::Sonstiges->value,
            'reason_text' => 'Eintrag wurde doppelt angelegt.',
            'request_type' => KassenbuchEditRequestType::Delete->value,
            'status' => KassenbuchEditRequest::STATUS_PENDING,
        ]);

        Mail::assertQueued(KassenbuchDeleteRequestSubmitted::class, fn ($mail) => $mail->hasTo($vorstandA->email)
            && $mail->hasTo($vorstandB->email)
            && ! $mail->hasTo($kassenwart->email));
    }

    public function test_delete_request_requires_non_blank_reason_text(): void
    {
        Mail::fake();

        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $entry = $this->createKassenbuchEntry($kassenwart);

        $response = $this->actingAs($kassenwart)
            ->post("/kassenbuch/eintrag/{$entry->id}/loeschung-anfragen", [
                'reason_text' => '   ',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('reason_text');
        Mail::assertNothingQueued();
    }

    public function test_cannot_request_delete_when_pending_request_exists_and_error_is_visible(): void
    {
        Mail::fake();

        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $entry = $this->createKassenbuchEntry($kassenwart);

        KassenbuchEditRequest::create([
            'kassenbuch_entry_id' => $entry->id,
            'requested_by' => $kassenwart->id,
            'reason_type' => KassenbuchEditReasonType::Sonstiges->value,
            'reason_text' => 'Bereits angefragt',
            'request_type' => KassenbuchEditRequestType::Delete->value,
            'status' => KassenbuchEditRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($kassenwart)
            ->from('/kassenbuch')
            ->followingRedirects()
            ->post("/kassenbuch/eintrag/{$entry->id}/loeschung-anfragen", [
                'reason_text' => 'Noch einmal löschen',
            ]);

        $response->assertOk();
        $response->assertSee('Für diesen Eintrag existiert bereits eine offene oder freigegebene Anfrage.');
        Mail::assertNothingQueued();
    }

    public function test_vorstand_cannot_request_delete(): void
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $vorstand = $this->createUserWithRole(Role::Vorstand);
        $entry = $this->createKassenbuchEntry($kassenwart);

        $response = $this->actingAs($vorstand)
            ->post("/kassenbuch/eintrag/{$entry->id}/loeschung-anfragen", [
                'reason_text' => 'Soll gelöscht werden',
            ]);

        $response->assertForbidden();
    }

    public function test_cannot_request_delete_for_entry_from_other_team(): void
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);

        $otherTeam = Team::factory()->create();
        $otherEntry = KassenbuchEntry::factory()->create([
            'team_id' => $otherTeam->id,
        ]);

        $response = $this->actingAs($kassenwart)
            ->post("/kassenbuch/eintrag/{$otherEntry->id}/loeschung-anfragen", [
                'reason_text' => 'Falscher Datensatz',
            ]);

        $response->assertNotFound();
    }

    public function test_vorstand_can_approve_delete_request_and_soft_delete_entry(): void
    {
        Mail::fake();

        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $vorstandA = $this->createUserWithRole(Role::Vorstand);
        $vorstandB = $this->createUserWithRole(Role::Vorstand);

        Kassenstand::where('team_id', $this->team->id)->update(['betrag' => 150.00]);
        $entry = $this->createKassenbuchEntry($kassenwart, 50.00);

        $deleteRequest = KassenbuchEditRequest::create([
            'kassenbuch_entry_id' => $entry->id,
            'requested_by' => $kassenwart->id,
            'reason_type' => KassenbuchEditReasonType::Sonstiges->value,
            'reason_text' => 'Eintrag wurde doppelt angelegt.',
            'request_type' => KassenbuchEditRequestType::Delete->value,
            'status' => KassenbuchEditRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($vorstandA)
            ->post("/kassenbuch/anfrage/{$deleteRequest->id}/freigeben");

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Löschung wurde freigegeben und durchgeführt.');

        $this->assertSoftDeleted('kassenbuch_entries', ['id' => $entry->id]);

        $deleteRequest->refresh();
        $this->assertSame(KassenbuchEditRequest::STATUS_APPROVED, $deleteRequest->status);
        $this->assertSame($vorstandA->id, $deleteRequest->processed_by);

        $this->assertSame(100.0, (float) Kassenstand::where('team_id', $this->team->id)->value('betrag'));

        Mail::assertQueued(KassenbuchDeleteApproved::class, fn ($mail) => $mail->hasTo($kassenwart->email)
            && $mail->hasTo($vorstandA->email)
            && $mail->hasTo($vorstandB->email));
    }

    public function test_vorstand_can_approve_delete_request_without_existing_kassenstand_row(): void
    {
        Mail::fake();

        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $vorstand = $this->createUserWithRole(Role::Vorstand);

        Kassenstand::where('team_id', $this->team->id)->delete();

        $deletedEntry = $this->createKassenbuchEntry($kassenwart, 50.00);
        $remainingEntry = $this->createKassenbuchEntry($kassenwart, 25.00);

        $deleteRequest = KassenbuchEditRequest::create([
            'kassenbuch_entry_id' => $deletedEntry->id,
            'requested_by' => $kassenwart->id,
            'reason_type' => KassenbuchEditReasonType::Sonstiges->value,
            'reason_text' => 'Eintrag wurde doppelt angelegt.',
            'request_type' => KassenbuchEditRequestType::Delete->value,
            'status' => KassenbuchEditRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($vorstand)
            ->post("/kassenbuch/anfrage/{$deleteRequest->id}/freigeben");

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Löschung wurde freigegeben und durchgeführt.');

        $this->assertSoftDeleted('kassenbuch_entries', ['id' => $deletedEntry->id]);
        $this->assertDatabaseHas('kassenbuch_entries', ['id' => $remainingEntry->id]);
        $this->assertDatabaseHas('kassenstand', [
            'team_id' => $this->team->id,
            'betrag' => 25.00,
        ]);
    }

    public function test_delete_approval_reverses_ausgabe_in_kassenstand(): void
    {
        Mail::fake();

        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $vorstand = $this->createUserWithRole(Role::Vorstand);

        Kassenstand::where('team_id', $this->team->id)->update(['betrag' => 30.00]);
        $entry = $this->createKassenbuchEntry($kassenwart, -20.00);

        $deleteRequest = KassenbuchEditRequest::create([
            'kassenbuch_entry_id' => $entry->id,
            'requested_by' => $kassenwart->id,
            'reason_type' => KassenbuchEditReasonType::Sonstiges->value,
            'reason_text' => 'Falsche Ausgabe',
            'request_type' => KassenbuchEditRequestType::Delete->value,
            'status' => KassenbuchEditRequest::STATUS_PENDING,
        ]);

        $this->actingAs($vorstand)
            ->post("/kassenbuch/anfrage/{$deleteRequest->id}/freigeben")
            ->assertRedirect();

        $this->assertSame(50.0, (float) Kassenstand::where('team_id', $this->team->id)->value('betrag'));
    }

    public function test_vorstand_can_reject_delete_request_and_mail_requester(): void
    {
        Mail::fake();

        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $vorstand = $this->createUserWithRole(Role::Vorstand);
        $entry = $this->createKassenbuchEntry($kassenwart);

        $deleteRequest = KassenbuchEditRequest::create([
            'kassenbuch_entry_id' => $entry->id,
            'requested_by' => $kassenwart->id,
            'reason_type' => KassenbuchEditReasonType::Sonstiges->value,
            'reason_text' => 'Eintrag wurde doppelt angelegt.',
            'request_type' => KassenbuchEditRequestType::Delete->value,
            'status' => KassenbuchEditRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($vorstand)
            ->post("/kassenbuch/anfrage/{$deleteRequest->id}/ablehnen", [
                'rejection_reason' => 'Bitte stattdessen korrigieren.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Löschanfrage wurde abgelehnt.');

        $deleteRequest->refresh();
        $this->assertSame(KassenbuchEditRequest::STATUS_REJECTED, $deleteRequest->status);
        $this->assertSame('Bitte stattdessen korrigieren.', $deleteRequest->rejection_reason);
        $this->assertDatabaseHas('kassenbuch_entries', ['id' => $entry->id]);

        Mail::assertQueued(KassenbuchDeleteRejected::class, fn ($mail) => $mail->hasTo($kassenwart->email)
            && ! $mail->hasTo($vorstand->email));
    }

    public function test_soft_deleted_entries_are_hidden_from_kassenbuch_index(): void
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $visibleEntry = $this->createKassenbuchEntry($kassenwart, 25.00);
        $deletedEntry = KassenbuchEntry::create([
            'team_id' => $this->team->id,
            'created_by' => $kassenwart->id,
            'buchungsdatum' => now(),
            'betrag' => 10.00,
            'beschreibung' => 'Versteckter Eintrag',
            'typ' => KassenbuchEntryType::Einnahme->value,
        ]);
        $deletedEntry->delete();

        $response = $this->actingAs($kassenwart)->get('/kassenbuch');

        $response->assertOk();
        $response->assertSee($visibleEntry->beschreibung);
        $response->assertDontSee('Versteckter Eintrag');
    }

    public function test_kassenwart_sees_pending_delete_badge_in_kassenbuch_index(): void
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $entry = $this->createKassenbuchEntry($kassenwart);

        KassenbuchEditRequest::create([
            'kassenbuch_entry_id' => $entry->id,
            'requested_by' => $kassenwart->id,
            'reason_type' => KassenbuchEditReasonType::Sonstiges->value,
            'reason_text' => 'Eintrag wurde doppelt angelegt.',
            'request_type' => KassenbuchEditRequestType::Delete->value,
            'status' => KassenbuchEditRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($kassenwart)->get('/kassenbuch');

        $response->assertOk();
        $response->assertSee('Löschanfrage läuft');
        $response->assertDontSee('Löschen anfragen');
    }

    public function test_vorstand_sees_delete_request_in_pending_requests_panel(): void
    {
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $vorstand = $this->createUserWithRole(Role::Vorstand);
        $entry = $this->createKassenbuchEntry($kassenwart);

        KassenbuchEditRequest::create([
            'kassenbuch_entry_id' => $entry->id,
            'requested_by' => $kassenwart->id,
            'reason_type' => KassenbuchEditReasonType::Sonstiges->value,
            'reason_text' => 'Eintrag wurde doppelt angelegt.',
            'request_type' => KassenbuchEditRequestType::Delete->value,
            'status' => KassenbuchEditRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($vorstand)->get('/kassenbuch');

        $response->assertOk();
        $response->assertSee('Offene Freigabeanfragen (1)');
        $response->assertSee('Löschung');
        $response->assertSee('Eintrag wurde doppelt angelegt.');
        $response->assertSee('Anfrage ablehnen');
        $response->assertSee('Angefragter Vorgang:');
        $response->assertDontSee('Bearbeitungsanfrage ablehnen');
    }
}