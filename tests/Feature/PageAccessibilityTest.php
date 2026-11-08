<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('public pages are accessible', function (): void {
    $urls = ['/', '/satzung', '/chronik', '/ehrenmitglieder', '/termine', '/arbeitsgruppen', '/mitglied-werden', '/spenden', '/impressum', '/datenschutz', '/changelog', '/mitglied-werden/erfolgreich', '/mitglied-werden/bestaetigt'];
    foreach ($urls as $url) {
        $this->get($url)->assertOk();
    }
});

test('home page shows correct member count', function (): void {
    $team = Team::membersTeam();
    $team->users()->attach(User::factory()->create(), ['role' => Role::Mitglied->value]);
    $team->users()->attach(User::factory()->create(), ['role' => Role::Anwaerter->value]);
    $response = $this->get('/');
    $response->assertOk();
    $response->assertSee('2');
});

test('impressum page shows contact information', function (): void {
    $this->get('/impressum')->assertOk()->assertSee('vorstand@maddrax-fanclub.de')->assertSee('Angaben gemäß §5 TMG');
});

test('datenschutz page displays data protection details', function (): void {
    $this->get('/datenschutz')->assertOk()->assertSee('Datenschutzerklärung')->assertSee('Verantwortlicher')->assertSee('Zweck der Verarbeitung');
});

test('spenden page contains paypal form', function (): void {
    $this->get('/spenden')->assertOk()->assertSee('kassenwart@maddrax-fanclub.de')->assertSee('paypal.com');
});

test('arbeitsgruppen page shows ag info', function (): void {
    $leader = User::factory()->create();
    Team::factory()->create(['name' => 'AG Test', 'user_id' => $leader->id, 'personal_team' => false, 'description' => 'Beschreibung der AG']);
    $this->get('/arbeitsgruppen')->assertOk()->assertSee('AG Test')->assertSee('Beschreibung der AG');
});
