<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\KassenbuchEntryType;
use App\Enums\Role;
use App\Livewire\KassenbuchIndex;
use App\Models\KassenbuchEntry;
use App\Models\Kassenstand;
use App\Models\Team;
use App\Models\User;
use App\Services\MembersTeamProvider;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CreatesUserWithRole::class, RefreshDatabase::class);

test('add entry updates balance', function (): void {
    $user = $this->actingKassenwart();
    // initialize kassenstand
    $this->get('/kassenbuch');
    $response = $this->post('/kassenbuch/eintrag-hinzufuegen', ['buchungsdatum' => '2025-01-01', 'betrag' => 5, 'beschreibung' => 'Beitrag', 'typ' => KassenbuchEntryType::Einnahme->value]);
    $response->assertRedirect();
    $this->assertDatabaseHas('kassenbuch_entries', ['beschreibung' => 'Beitrag', 'betrag' => 5.0]);
    $this->assertDatabaseHas('kassenstand', ['betrag' => 5.0]);
});

test('update payment updates membership since', function (): void {
    $kassenwart = $this->actingKassenwart();
    $team = $kassenwart->currentTeam;
    $member = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($member, ['role' => Role::Mitglied->value]);
    $response = $this->from('/kassenbuch')->put("/kassenbuch/zahlung-aktualisieren/{$member->id}", ['mitgliedsbeitrag' => 42, 'bezahlt_bis' => '2025-12-31', 'mitglied_seit' => '2024-01-01']);
    $response->assertRedirect('/kassenbuch');
    $member->refresh();
    expect($member->bezahlt_bis->format('Y-m-d'))->toEqual('2025-12-31')
        ->and($member->mitglied_seit->format('Y-m-d'))->toEqual('2024-01-01')
        ->and($member->mitgliedsbeitrag)->toEqual(42.0);
});

test('index returns page for kassenwart', function (): void {
    $kassenwart = $this->actingKassenwart();
    $team = $kassenwart->currentTeam;
    $member = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($member, ['role' => Role::Mitglied->value]);
    KassenbuchEntry::create(['team_id' => $team->id, 'created_by' => $kassenwart->id, 'buchungsdatum' => now(), 'betrag' => 5, 'beschreibung' => 'Beitrag', 'typ' => KassenbuchEntryType::Einnahme->value]);
    $response = $this->get('/kassenbuch');
    $response->assertOk();
    $response->assertSee('Kassenbuch');
    $response->assertSee('Beitrag');
    $response->assertSee($member->name);
});

test('member cannot update payment status', function (): void {
    $member = $this->actingMember();
    $this->actingAs($member);
    $team = $member->currentTeam;
    $target = User::factory()->create(['current_team_id' => $team->id, 'bezahlt_bis' => '2024-06-30', 'mitglied_seit' => '2020-01-01', 'mitgliedsbeitrag' => 36.0]);
    $team->users()->attach($target, ['role' => Role::Mitglied->value]);
    $response = $this->from('/kassenbuch')->put("/kassenbuch/zahlung-aktualisieren/{$target->id}", ['mitgliedsbeitrag' => 42, 'bezahlt_bis' => '2025-12-31', 'mitglied_seit' => '2024-01-01']);
    $response->assertForbidden();
    $target->refresh();
    expect($target->bezahlt_bis->format('Y-m-d'))->toEqual('2024-06-30')
        ->and($target->mitglied_seit->format('Y-m-d'))->toEqual('2020-01-01')
        ->and($target->mitgliedsbeitrag)->toEqual(36.0);
});

test('member cannot add entry', function (): void {
    $member = $this->actingMember();
    $this->actingAs($member);
    $response = $this->from('/kassenstand')->post('/kassenbuch/eintrag-hinzufuegen', ['buchungsdatum' => '2025-01-01', 'betrag' => 5, 'beschreibung' => 'Beitrag', 'typ' => KassenbuchEntryType::Einnahme->value]);
    $response->assertForbidden();
    $this->assertDatabaseCount('kassenbuch_entries', 0);
});

test('kassenbuch forms have accessibility attributes', function (): void {
    $kassenwart = $this->actingKassenwart();
    $response = $this->get('/kassenbuch');
    $response->assertSee('aria-describedby="mitgliedsbeitrag-error"', false);
    $response->assertSee('aria-describedby="buchungsdatum-error"', false);
});

test('add entry requires fields', function (): void {
    $kassenwart = $this->actingKassenwart();
    // initialize kassenstand
    $this->get('/kassenbuch');
    $response = $this->from('/kassenbuch')->post('/kassenbuch/eintrag-hinzufuegen', []);
    $response->assertRedirect('/kassenbuch');
    $response->assertSessionHasErrors(['buchungsdatum', 'betrag', 'beschreibung', 'typ']);
});

test('add entry rejects zero amount value', function (): void {
    $kassenwart = $this->actingKassenwart();
    $this->get('/kassenbuch');
    $response = $this->from('/kassenbuch')->post('/kassenbuch/eintrag-hinzufuegen', ['buchungsdatum' => '2025-02-01', 'betrag' => 0, 'beschreibung' => 'Unzulässiger Eintrag', 'typ' => KassenbuchEntryType::Einnahme->value]);
    $response->assertRedirect('/kassenbuch');
    $response->assertSessionHasErrors(['betrag']);
    $this->assertDatabaseCount('kassenbuch_entries', 0);
});

test('add entry rejects unknown type', function (): void {
    $kassenwart = $this->actingKassenwart();
    $this->get('/kassenbuch');
    $response = $this->from('/kassenbuch')->post('/kassenbuch/eintrag-hinzufuegen', ['buchungsdatum' => '2025-02-02', 'betrag' => 10, 'beschreibung' => 'Ungültiger Typ', 'typ' => 'spende']);
    $response->assertRedirect('/kassenbuch');
    $response->assertSessionHasErrors(['typ']);
    $this->assertDatabaseCount('kassenbuch_entries', 0);
});

test('add entry normalizes expense amount', function (): void {
    $kassenwart = $this->actingKassenwart();
    $this->get('/kassenbuch');
    $response = $this->post('/kassenbuch/eintrag-hinzufuegen', ['buchungsdatum' => '2025-03-01', 'betrag' => 12.5, 'beschreibung' => 'Ausgabe für Material', 'typ' => KassenbuchEntryType::Ausgabe->value]);
    $response->assertRedirect();
    $this->assertDatabaseHas('kassenbuch_entries', ['beschreibung' => 'Ausgabe für Material', 'betrag' => -12.5]);
    $this->assertDatabaseHas('kassenstand', ['team_id' => $kassenwart->currentTeam->id, 'betrag' => -12.5]);
});

test('add entry makes income positive even when negative value is submitted', function (): void {
    $kassenwart = $this->actingKassenwart();
    $this->get('/kassenbuch');
    $response = $this->post('/kassenbuch/eintrag-hinzufuegen', ['buchungsdatum' => '2025-04-01', 'betrag' => -25, 'beschreibung' => 'Nachträgliche Einnahme', 'typ' => KassenbuchEntryType::Einnahme->value]);
    $response->assertRedirect();
    $this->assertDatabaseHas('kassenbuch_entries', ['beschreibung' => 'Nachträgliche Einnahme', 'betrag' => 25.0]);
    $this->assertDatabaseHas('kassenstand', ['team_id' => $kassenwart->currentTeam->id, 'betrag' => 25.0]);
});

test('add entry updates kassenstand timestamp within transaction', function (): void {
    $kassenwart = $this->actingKassenwart();
    $this->get('/kassenbuch');
    $team = $kassenwart->currentTeam;
    $kassenstand = Kassenstand::where('team_id', $team->id)->firstOrFail();
    $originalTimestamp = $kassenstand->letzte_aktualisierung;
    $freezeTime = Carbon::now()->addDay();
    Carbon::setTestNow($freezeTime);
    $response = $this->post('/kassenbuch/eintrag-hinzufuegen', ['buchungsdatum' => '2025-05-01', 'betrag' => 10, 'beschreibung' => 'Zeitgesteuerte Einnahme', 'typ' => KassenbuchEntryType::Einnahme->value]);
    $response->assertRedirect();
    $kassenstand->refresh();
    Carbon::setTestNow();
    expect($kassenstand->betrag)->toEqual('10.00')
        ->and($kassenstand->letzte_aktualisierung->isSameDay($freezeTime))->toBeTrue()
        ->and($kassenstand->letzte_aktualisierung->isSameDay($originalTimestamp))->toBeFalse();
});

test('update payment accepts null membership start', function (): void {
    $kassenwart = $this->actingKassenwart();
    $team = $kassenwart->currentTeam;
    $member = User::factory()->create(['current_team_id' => $team->id, 'mitglied_seit' => '2020-01-01']);
    $team->users()->attach($member, ['role' => Role::Mitglied->value]);
    $response = $this->from('/kassenbuch')->put("/kassenbuch/zahlung-aktualisieren/{$member->id}", ['mitgliedsbeitrag' => 48, 'bezahlt_bis' => '2026-01-01', 'mitglied_seit' => null]);
    $response->assertRedirect('/kassenbuch');
    $member->refresh();
    expect($member->mitglied_seit)->toBeNull()
        ->and($member->bezahlt_bis->format('Y-m-d'))->toEqual('2026-01-01')
        ->and($member->mitgliedsbeitrag)->toEqual(48.0);
});

test('index uses members team provider', function (): void {
    $team = Team::membersTeam();
    $this->mock(MembersTeamProvider::class, function ($mock) use ($team) {
        $mock->shouldReceive('getMembersTeamOrAbort')->atLeast()->once()->andReturn($team);
    });
    $user = $this->createUserWithRole(Role::Kassenwart);
    $this->actingAs($user);
    Livewire::test(KassenbuchIndex::class)->assertOk();
});

test('member cannot access kassenbuch', function (): void {
    $member = $this->actingMember();
    $this->actingAs($member);
    $response = $this->get('/kassenbuch');
    $response->assertForbidden();
});

test('vorstand can access kassenbuch', function (): void {
    $vorstand = $this->actingVorstand();
    $response = $this->get('/kassenbuch');
    $response->assertOk();
});

test('kassenwart can access kassenbuch', function (): void {
    $kassenwart = $this->actingKassenwart();
    $response = $this->get('/kassenbuch');
    $response->assertOk();
});

test('admin can access kassenbuch', function (): void {
    $admin = $this->actingAdmin();
    $response = $this->get('/kassenbuch');
    $response->assertOk();
});
