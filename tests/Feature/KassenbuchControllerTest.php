<?php

namespace Tests\Feature;

use App\Enums\KassenbuchEntryType;
use App\Models\Kassenstand;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Enums\Role;
use App\Services\MembersTeamProvider;
use Carbon\Carbon;

class KassenbuchControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingMember(string $role = 'Mitglied'): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => Role::from($role)->value]);

        return $user;
    }

    public function test_add_entry_updates_balance(): void
    {
        $user = $this->actingMember('Kassenwart');
        $this->actingAs($user);

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
        $kassenwart = $this->actingMember('Kassenwart');
        $this->actingAs($kassenwart);

        $team = $kassenwart->currentTeam;
        $member = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($member, ['role' => \App\Enums\Role::Mitglied->value]);

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

    public function test_index_creates_initial_kassenstand_and_shows_basic_data(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        $this->assertDatabaseMissing('kassenstand', ['team_id' => $user->currentTeam->id]);

        $response = $this->get('/kassenbuch');

        $response->assertOk();
        $this->assertDatabaseHas('kassenstand', ['team_id' => $user->currentTeam->id, 'betrag' => 0.00]);

        $response->assertViewHas('userRole', Role::Mitglied);
        $this->assertNull($response->viewData('members'));
        $this->assertNull($response->viewData('kassenbuchEntries'));
    }

    public function test_index_sets_renewal_warning_for_expiring_membership(): void
    {
        $user = $this->actingMember();
        $user->update(['bezahlt_bis' => now()->addDays(10)]);
        $this->actingAs($user);

        $response = $this->get('/kassenbuch');

        $response->assertOk();
        $response->assertViewHas('renewalWarning', true);
    }

    public function test_index_does_not_set_warning_when_membership_is_far_in_future(): void
    {
        $user = $this->actingMember();
        $user->update(['bezahlt_bis' => now()->addDays(45)]);
        $this->actingAs($user);

        $response = $this->get('/kassenbuch');

        $response->assertOk();
        $response->assertViewHas('renewalWarning', false);
    }

    public function test_index_marks_membership_as_expired_without_warning(): void
    {
        $user = $this->actingMember();
        $user->update(['bezahlt_bis' => now()->subDays(5)]);
        $this->actingAs($user);

        $response = $this->get('/kassenbuch');

        $response->assertOk();
        $response->assertViewHas('renewalWarning', false);
        $response->assertSee('Deine Mitgliedschaft ist abgelaufen!', false);
    }

    public function test_index_returns_members_and_entries_for_kassenwart(): void
    {
        $kassenwart = $this->actingMember('Kassenwart');
        $this->actingAs($kassenwart);

        $team = $kassenwart->currentTeam;
        $member = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($member, ['role' => \App\Enums\Role::Mitglied->value]);

        \App\Models\KassenbuchEntry::create([
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
        $team->users()->attach($target, ['role' => \App\Enums\Role::Mitglied->value]);

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

        // initialize kassenstand
        $this->get('/kassenbuch');

        $response = $this->from('/kassenbuch')->post('/kassenbuch/eintrag-hinzufuegen', [
            'buchungsdatum' => '2025-01-01',
            'betrag' => 5,
            'beschreibung' => 'Beitrag',
            'typ' => KassenbuchEntryType::Einnahme->value,
        ]);

        $response->assertForbidden();

        $this->assertDatabaseCount('kassenbuch_entries', 0);
        $this->assertDatabaseHas('kassenstand', [
            'team_id' => $member->currentTeam->id,
            'betrag' => 0.00,
        ]);
    }

    public function test_kassenbuch_forms_have_accessibility_attributes(): void
    {
        $kassenwart = $this->actingMember('Kassenwart');
        $this->actingAs($kassenwart);

        $response = $this->get('/kassenbuch');

        $response->assertSee('aria-describedby="mitgliedsbeitrag-error"', false);
        $response->assertSee('aria-describedby="buchungsdatum-error"', false);
    }

    public function test_add_entry_requires_fields(): void
    {
        $kassenwart = $this->actingMember('Kassenwart');
        $this->actingAs($kassenwart);

        // initialize kassenstand
        $this->get('/kassenbuch');

        $response = $this->from('/kassenbuch')->post('/kassenbuch/eintrag-hinzufuegen', []);

        $response->assertRedirect('/kassenbuch');
        $response->assertSessionHasErrors(['buchungsdatum', 'betrag', 'beschreibung', 'typ']);
    }

    public function test_add_entry_rejects_zero_amount_value(): void
    {
        $kassenwart = $this->actingMember('Kassenwart');
        $this->actingAs($kassenwart);

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
        $kassenwart = $this->actingMember('Kassenwart');
        $this->actingAs($kassenwart);

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
        $kassenwart = $this->actingMember('Kassenwart');
        $this->actingAs($kassenwart);

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
        $kassenwart = $this->actingMember('Kassenwart');
        $this->actingAs($kassenwart);

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
        $kassenwart = $this->actingMember('Kassenwart');
        $this->actingAs($kassenwart);

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
        $kassenwart = $this->actingMember('Kassenwart');
        $this->actingAs($kassenwart);

        $team = $kassenwart->currentTeam;
        $member = User::factory()->create([
            'current_team_id' => $team->id,
            'mitglied_seit' => '2020-01-01',
        ]);
        $team->users()->attach($member, ['role' => \App\Enums\Role::Mitglied->value]);

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
        $user = $this->actingMember();
        $this->actingAs($user);

        $this->mock(MembersTeamProvider::class, function ($mock) use ($team) {
            $mock->shouldReceive('getMembersTeamOrAbort')->once()->andReturn($team);
        });

        $this->get('/kassenbuch')->assertOk();
    }
}
