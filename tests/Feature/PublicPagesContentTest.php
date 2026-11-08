<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('chronik page shows heading', function (): void {
    $this->get('/chronik')->assertOk()->assertSee('Chronik des Offiziellen MADDRAX Fanclub e. V.');
});

test('ehrenmitglieder page shows member names', function (): void {
    $this->get('/ehrenmitglieder')->assertOk()->assertSee('Ehrenmitglieder')->assertSee('Michael Edelbrock');
});

test('satzung page shows sections', function (): void {
    $this->get('/satzung')->assertOk()->assertSee('Satzung des Offiziellen MADDRAX Fanclub e.V.')->assertSee('§1 Name', false);
});

test('mitglied werden page shows form fields', function (): void {
    $this->get('/mitglied-werden')->assertOk()->assertSee('Mitglied werden')->assertSee('Vorname')->assertSee('Nachname');
});

test('mitglied werden erfolgreich page shows success message', function (): void {
    $this->get('/mitglied-werden/erfolgreich')->assertOk()->assertSee('Antrag erfolgreich eingereicht');
});

test('mitglied werden bestaetigt page shows confirmation message', function (): void {
    $this->get('/mitglied-werden/bestaetigt')->assertOk()->assertSee('Vielen Dank für deine Bestätigung!');
});

test('spenden page shows heading and description', function (): void {
    $this->get('/spenden')->assertOk()->assertSee('Spenden')->assertSee('Spenden helfen uns bei der Finanzierung der jährlichen Fantreffen sowie der Serverkosten dieser Webseite')->assertSee('kassenwart@maddrax-fanclub.de');
});

test('arbeitsgruppen page shows leader schedule and contact', function (): void {
    $leader = User::factory()->create(['name' => 'Martin Gobrecht', 'vorname' => 'Martin']);
    $ag = Team::factory()->create(['name' => 'AG Öffentlichkeit', 'user_id' => $leader->id, 'personal_team' => false, 'description' => 'Beschreibung der AG', 'meeting_schedule' => 'jeden Dienstag', 'email' => 'ag@example.com']);
    $this->get('/arbeitsgruppen')->assertOk()->assertSee('AG Öffentlichkeit')->assertSee('Beschreibung der AG')->assertSeeText('Martin')->assertDontSeeText('Martin Gobrecht')->assertSee('jeden Dienstag')->assertSeeText('Kontakt aufnehmen')->assertSee('href="'.route('arbeitsgruppen.kontakt', $ag).'"', false)->assertSee('aria-label="Kontakt zur Arbeitsgruppe AG Öffentlichkeit aufnehmen"', false)->assertDontSee('ag@example.com', false)->assertDontSee('mailto:ag@example.com', false);
});
