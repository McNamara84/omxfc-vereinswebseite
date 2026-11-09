<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Kassenstand;
use App\Models\Team;
use App\Services\MembersTeamProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CreatesUserWithRole::class, RefreshDatabase::class);

test('mitglied kann kassenstand sehen', function (): void {
    $this->actingMember();
    $response = $this->get('/kassenstand');
    $response->assertOk();
    $response->assertViewIs('kassenbuch.kassenstand');
});

test('kassenstand zeigt mitgliedsbeitrag', function (): void {
    $this->actingMember(attributes: ['mitgliedsbeitrag' => 36.0]);
    $response = $this->get('/kassenstand');
    $response->assertOk();
    $response->assertSee('Dein Mitgliedsbeitrag');
    $response->assertSee('36,00');
});

test('kassenstand zeigt aktuellen kassenstand', function (): void {
    $user = $this->actingMember();
    $team = $user->currentTeam;
    Kassenstand::create(['team_id' => $team->id, 'betrag' => 1234.56, 'letzte_aktualisierung' => now()]);
    $response = $this->get('/kassenstand');
    $response->assertOk();
    $response->assertSee('Aktueller Kassenstand');
    $response->assertSee('1.234,56');
});

test('kassenstand zeigt keine verwaltung', function (): void {
    $this->actingMember();
    $response = $this->get('/kassenstand');
    $response->assertOk();
    $response->assertDontSee('Eintrag hinzufügen');
    $response->assertDontSee('Zahlung aktualisieren');
});

test('vorstand sieht kassenstand wie mitglied', function (): void {
    $this->actingVorstand();
    $response = $this->get('/kassenstand');
    $response->assertOk();
    $response->assertViewIs('kassenbuch.kassenstand');
    $response->assertSee('Dein Mitgliedsbeitrag');
    $response->assertSee('Aktueller Kassenstand');
});

test('gast wird zum login weitergeleitet', function (): void {
    $response = $this->get('/kassenstand');
    $response->assertRedirect('/login');
});

test('kassenstand erstellt initialen eintrag', function (): void {
    $user = $this->actingMember();
    $this->assertDatabaseMissing('kassenstand', ['team_id' => $user->currentTeam->id]);
    $response = $this->get('/kassenstand');
    $response->assertOk();
    $this->assertDatabaseHas('kassenstand', ['team_id' => $user->currentTeam->id, 'betrag' => 0.0]);
    $response->assertViewHas('renewalWarning', false);
});

test('kassenstand zeigt erneuerungswarnung bei ablaufender mitgliedschaft', function (): void {
    $user = $this->actingMember();
    $user->update(['bezahlt_bis' => now()->addDays(10)]);
    $this->actingAs($user);
    $response = $this->get('/kassenstand');
    $response->assertOk();
    $response->assertViewHas('renewalWarning', true);
});

test('kassenstand zeigt keine warnung bei langer mitgliedschaft', function (): void {
    $user = $this->actingMember();
    $user->update(['bezahlt_bis' => now()->addDays(45)]);
    $this->actingAs($user);
    $response = $this->get('/kassenstand');
    $response->assertOk();
    $response->assertViewHas('renewalWarning', false);
});

test('kassenstand markiert abgelaufene mitgliedschaft ohne warnung', function (): void {
    $user = $this->actingMember();
    $user->update(['bezahlt_bis' => now()->subDays(5)]);
    $this->actingAs($user);
    $response = $this->get('/kassenstand');
    $response->assertOk();
    $response->assertViewHas('renewalWarning', false);
    $response->assertSee('Deine Mitgliedschaft ist abgelaufen!', false);
});

test('kassenstand uses members team provider', function (): void {
    $team = Team::membersTeam();
    $this->mock(MembersTeamProvider::class, function ($mock) use ($team) {
        $mock->shouldReceive('getMembersTeamOrAbort')->once()->andReturn($team);
    });
    $user = $this->createUserWithRole(Role::Mitglied);
    $this->actingAs($user);
    $this->get('/kassenstand')->assertOk();
});
