<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(MitgliederIndexAccessibilityTestFixtures::class);

test('members table summary reflects sort and filters', function (): void {
    $admin = $this->createMember(Role::Admin);
    $team = Team::membersTeam();
    $onlineMember = User::factory()->create(['current_team_id' => $team->id, 'name' => 'Online Mitglied']);
    $team->users()->attach($onlineMember, ['role' => Role::Mitglied->value]);
    DB::table('sessions')->insert(['id' => Str::random(40), 'user_id' => $onlineMember->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'payload' => '', 'last_activity' => now()->timestamp]);
    $this->actingAs($admin);
    $response = $this->get('/mitglieder?sort=role&dir=desc&nurOnline=1');
    $response->assertOk();
    $html = $response->getContent();
    expect($html)->toContain('Mitgliederliste, sortiert nach Rolle in absteigender Reihenfolge.')
        ->toContain('Es werden nur Mitglieder angezeigt, die aktuell online sind.')
        ->toMatch('/Insgesamt sind \d+ Mitglied(?:er)? sichtbar\./');
});

test('members heading exposes data attribute for ui tests', function (): void {
    $admin = $this->createMember(Role::Admin);
    $this->actingAs($admin);
    $response = $this->get('/mitglieder');
    $response->assertOk();
    $crawler = new Crawler($response->getContent());
    $heading = $crawler->filter('[data-members-heading]')->first();
    expect(trim($heading->text()))->toBe('Mitgliederliste')
        ->and(strtoupper($heading->nodeName()))->toBe('H2');
});

test('table headers expose aria sort attributes', function (): void {
    $admin = $this->createMember(Role::Admin);
    $this->actingAs($admin);
    $response = $this->get('/mitglieder?sort=mitglied_seit&dir=desc');
    $response->assertOk();
    $crawler = new Crawler($response->getContent());
    $mitgliedSeitHeader = $crawler->filter('[data-members-sort-column="mitglied_seit"]')->first();
    expect($mitgliedSeitHeader->attr('aria-sort'))->toBe('descending')
        ->and($mitgliedSeitHeader->attr('scope'))->toBe('col');
    $nameHeader = $crawler->filter('[data-members-sort-column="name"]')->first();
    expect($nameHeader->attr('aria-sort'))->toBe('none');
});

test('members table includes dataset metadata', function (): void {
    $admin = $this->createMember(Role::Admin);
    $team = Team::membersTeam();
    $team->users()->attach(User::factory()->create(['current_team_id' => $team->id]), ['role' => Role::Mitglied->value]);
    $team->users()->attach(User::factory()->create(['current_team_id' => $team->id]), ['role' => Role::Mitglied->value]);
    $this->actingAs($admin);
    $response = $this->get('/mitglieder');
    $response->assertOk();
    $crawler = new Crawler($response->getContent());
    $table = $crawler->filter('[data-members-table]')->first();
    expect($table->attr('data-members-sort'))->toBe('name')
        ->and($table->attr('data-members-dir'))->toBe('asc')
        ->and($table->attr('data-members-total'))->toBe((string) $team->activeUsers()->count())
        ->and($table->attr('data-members-summary-id'))->toBe('members-table-summary');
});
