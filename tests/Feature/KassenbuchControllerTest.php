<?php

namespace Tests\Feature;

use App\Enums\KassenbuchEntryType;
use App\Enums\Role;
use App\Models\KassenbuchEntry;
use App\Models\Kassenstand;
use App\Models\Team;
use App\Models\User;
use App\Services\MembersTeamProvider;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class KassenbuchControllerTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    public function test_add_entry_updates_balance(): void
    {
        $user = $this->actingKassenwart();

        // initialize kassenstand
        $this->get('/kassenbuch');

        $response = $this->post('/kassenbuch/eintrag-hinzufuegen', [
            'buchungsdatum' => '2025-01-01',
            'betrag' => 5,
            'beschreibung' => 'Beitrag',
            'typ' => KassenbuchEntryType::Einnahme->value,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('kassenbuch_entries', [
            'beschreibung' => 'Beitrag',
            'betrag' => 5.00,
        ]);
        $this->assertDatabaseHas('kassenstand', [
            'betrag' => 5.00,
        ]);
    }

    public function test_update_payment_updates_membership_since(): void
    {
        $kassenwart = $this->actingKassenwart();

        $team = $kassenwart->currentTeam;
        $member = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($member, ['role' => Role::Mitglied->value]);

        $response = $this->from('/kassenbuch')->put("/kassenbuch/zahlung-aktualisieren/{$member->id}", [
            'mitgliedsbeitrag' => 42,
            'bezahlt_bis' => '2025-12-31',
            'mitglied_seit' => '2024-01-01',
        ]);

        $response->assertRedirect('/kassenbuch');

        $member->refresh();
        $this->assertEquals('2025-12-31', $member->bezahlt_bis->format('Y-m-d'));
        $this->assertEquals('2024-01-01', $member->mitglied_seit->format('Y-m-d'));
        $this->assertEquals(42.00, $member->mitgliedsbeitrag);
    }

    public function test_index_returns_members_and_entries_for_kassenwart(): void
    {
        $kassenwart = $this->actingKassenwart();

        $team = $kassenwart->currentTeam;
        $member = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($member, ['role' => Role::Mitglied->value]);

        KassenbuchEntry::create([
            'team_id' => $team->id,
            'created_by' => $kassenwart->id,
            'buchungsdatum' => now(),
            'betrag' => 5,
            'beschreibung' => 'Beitrag',
            'typ' => KassenbuchEntryType::Einnahme->value,
        ]);

        $response = $this->get('/kassenbuch');

        $response->assertOk();
        $response->assertViewHas('userRole', Role::Kassenwart);
        $response->assertViewHas('canViewKassenbuch', true);
        $response->assertViewHas('canManageKassenbuch', true);
        $this->assertNotNull($response->viewData('members'));
        $entries = $response->viewData('kassenbuchEntries');
        $this->assertCount(1, $entries);
    }

    public function test_member_cannot_update_payment_status(): void
    {
        $member = $this->actingMember();
        $this->actingAs($member);

        $team = $member->currentTeam;
        $target = User::factory()->create([
            'current_team_id' => $team->id,
            'bezahlt_bis' => '2024-06-30',
            'mitglied_seit' => '2020-01-01',
            'mitgliedsbeitrag' => 36.00,
        ]);
        $team->users()->attach($target, ['role' => Role::Mitglied->value]);

        $response = $this->from('/kassenbuch')->put("/kassenbuch/zahlung-aktualisieren/{$target->id}", [
            'mitgliedsbeitrag' => 42,
            'bezahlt_bis' => '2025-12-31',
            'mitglied_seit' => '2024-01-01',
        ]);

        $response->assertForbidden();

        $target->refresh();
        $this->assertEquals('2024-06-30', $target->bezahlt_bis->format('Y-m-d'));
        $this->assertEquals('2020-01-01', $target->mitglied_seit->format('Y-m-d'));
        $this->assertEquals(36.00, $target->mitgliedsbeitrag);
    }

    public function test_member_cannot_add_entry(): void
    {
        $member = $this->actingMember();
        $this->actingAs($member);

        $response = $this->from('/kassenstand')->post('/kassenbuch/eintrag-hinzufuegen', [
            'buchungsdatum' => '2025-01-01',
            'betrag' => 5,
            'beschreibung' => 'Beitrag',
            'typ' => KassenbuchEntryType::Einnahme->value,
        ]);

        $response->assertForbidden();

        $this->assertDatabaseCount('kassenbuch_entries', 0);
    }

    public function test_kassenbuch_forms_have_accessibility_attributes(): void
    {
        $kassenwart = $this->actingKassenwart();

        $response = $this->get('/kassenbuch');

        $response->assertSee('aria-describedby="mitgliedsbeitrag-error"', false);
        $response->assertSee('aria-describedby="buchungsdatum-error"', false);
    }

    public function test_add_entry_requires_fields(): void
    {
        $kassenwart = $this->actingKassenwart();

        // initialize kassenstand
        $this->get('/kassenbuch');

        $response = $this->from('/kassenbuch')->post('/kassenbuch/eintrag-hinzufuegen', []);

        $response->assertRedirect('/kassenbuch');
        $response->assertSessionHasErrors(['buchungsdatum', 'betrag', 'beschreibung', 'typ']);
    }

    public function test_add_entry_rejects_zero_amount_value(): void
    {
        $kassenwart = $this->actingKassenwart();

        $this->get('/kassenbuch');

        $response = $this->from('/kassenbuch')->post('/kassenbuch/eintrag-hinzufuegen', [
            'buchungsdatum' => '2025-02-01',
            'betrag' => 0,
            'beschreibung' => 'Unzulässiger Eintrag',
            'typ' => KassenbuchEntryType::Einnahme->value,
        ]);

        $response->assertRedirect('/kassenbuch');
        $response->assertSessionHasErrors(['betrag']);
        $this->assertDatabaseCount('kassenbuch_entries', 0);
    }

    public function test_add_entry_rejects_unknown_type(): void
    {
        $kassenwart = $this->actingKassenwart();

        $this->get('/kassenbuch');

        $response = $this->from('/kassenbuch')->post('/kassenbuch/eintrag-hinzufuegen', [
            'buchungsdatum' => '2025-02-02',
            'betrag' => 10,
            'beschreibung' => 'Ungültiger Typ',
            'typ' => 'spende',
        ]);

        $response->assertRedirect('/kassenbuch');
        $response->assertSessionHasErrors(['typ']);
        $this->assertDatabaseCount('kassenbuch_entries', 0);
    }

    public function test_add_entry_normalizes_expense_amount(): void
    {
        $kassenwart = $this->actingKassenwart();

        $this->get('/kassenbuch');

        $response = $this->post('/kassenbuch/eintrag-hinzufuegen', [
            'buchungsdatum' => '2025-03-01',
            'betrag' => 12.5,
            'beschreibung' => 'Ausgabe für Material',
            'typ' => KassenbuchEntryType::Ausgabe->value,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('kassenbuch_entries', [
            'beschreibung' => 'Ausgabe für Material',
            'betrag' => -12.5,
        ]);

        $this->assertDatabaseHas('kassenstand', [
            'team_id' => $kassenwart->currentTeam->id,
            'betrag' => -12.5,
        ]);
    }

    public function test_add_entry_makes_income_positive_even_when_negative_value_is_submitted(): void
    {
        $kassenwart = $this->actingKassenwart();

        $this->get('/kassenbuch');

        $response = $this->post('/kassenbuch/eintrag-hinzufuegen', [
            'buchungsdatum' => '2025-04-01',
            'betrag' => -25,
            'beschreibung' => 'Nachträgliche Einnahme',
            'typ' => KassenbuchEntryType::Einnahme->value,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('kassenbuch_entries', [
            'beschreibung' => 'Nachträgliche Einnahme',
            'betrag' => 25.00,
        ]);

        $this->assertDatabaseHas('kassenstand', [
            'team_id' => $kassenwart->currentTeam->id,
            'betrag' => 25.00,
        ]);
    }

    public function test_add_entry_updates_kassenstand_timestamp_within_transaction(): void
    {
        $kassenwart = $this->actingKassenwart();

        $this->get('/kassenbuch');
        $team = $kassenwart->currentTeam;

        $kassenstand = Kassenstand::where('team_id', $team->id)->firstOrFail();
        $originalTimestamp = $kassenstand->letzte_aktualisierung;

        $freezeTime = Carbon::now()->addDay();
        Carbon::setTestNow($freezeTime);

        $response = $this->post('/kassenbuch/eintrag-hinzufuegen', [
            'buchungsdatum' => '2025-05-01',
            'betrag' => 10,
            'beschreibung' => 'Zeitgesteuerte Einnahme',
            'typ' => KassenbuchEntryType::Einnahme->value,
        ]);

        $response->assertRedirect();

        $kassenstand->refresh();

        Carbon::setTestNow();

        $this->assertEquals('10.00', $kassenstand->betrag);
        $this->assertTrue($kassenstand->letzte_aktualisierung->isSameDay($freezeTime));
        $this->assertFalse($kassenstand->letzte_aktualisierung->isSameDay($originalTimestamp));
    }

    public function test_update_payment_accepts_null_membership_start(): void
    {
        $kassenwart = $this->actingKassenwart();

        $team = $kassenwart->currentTeam;
        $member = User::factory()->create([
            'current_team_id' => $team->id,
            'mitglied_seit' => '2020-01-01',
        ]);
        $team->users()->attach($member, ['role' => Role::Mitglied->value]);

        $response = $this->from('/kassenbuch')->put("/kassenbuch/zahlung-aktualisieren/{$member->id}", [
            'mitgliedsbeitrag' => 48,
            'bezahlt_bis' => '2026-01-01',
            'mitglied_seit' => null,
        ]);

        $response->assertRedirect('/kassenbuch');

        $member->refresh();
        $this->assertNull($member->mitglied_seit);
        $this->assertEquals('2026-01-01', $member->bezahlt_bis->format('Y-m-d'));
        $this->assertEquals(48.00, $member->mitgliedsbeitrag);
    }

    public function test_index_uses_members_team_provider(): void
    {
        $team = Team::membersTeam();

        $this->mock(MembersTeamProvider::class, function ($mock) use ($team) {
            $mock->shouldReceive('getMembersTeamOrAbort')->once()->andReturn($team);
        });

        $user = $this->createUserWithRole(Role::Kassenwart);
        $this->actingAs($user);

        $this->get('/kassenbuch')->assertOk();
    }

    public function test_member_cannot_access_kassenbuch(): void
    {
        $member = $this->actingMember();
        $this->actingAs($member);

        $response = $this->get('/kassenbuch');

        $response->assertForbidden();
    }

    public function test_vorstand_can_access_kassenbuch(): void
    {
        $vorstand = $this->actingVorstand();

        $response = $this->get('/kassenbuch');

        $response->assertOk();
    }

    public function test_kassenwart_can_access_kassenbuch(): void
    {
        $kassenwart = $this->actingKassenwart();

        $response = $this->get('/kassenbuch');

        $response->assertOk();
    }

    public function test_admin_can_access_kassenbuch(): void
    {
        $admin = $this->actingAdmin();

        $response = $this->get('/kassenbuch');

        $response->assertOk();
    }
}
