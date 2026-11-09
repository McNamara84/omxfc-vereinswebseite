<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AudiobookEpisode;
use App\Models\User;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(HoerbuchPublicAccessTestFixtures::class);

test('guest can view index', function (): void {
    $episode = $this->createEpisodeWithRoles();
    $this->get(route('hoerbuecher.index'))->assertOk()->assertSee('Hörbuchfolgen')->assertSee($episode->title);
});

test('guest can view episode detail', function (): void {
    $episode = $this->createEpisodeWithRoles();
    $this->get(route('hoerbuecher.show', $episode))->assertOk()->assertSee($episode->title)->assertSee($episode->author)->assertSee($episode->episode_number);
});

test('guest does not see create button on index', function (): void {
    $this->createEpisodeWithRoles();
    $this->get(route('hoerbuecher.index'))->assertOk()->assertDontSee(route('hoerbuecher.create'))->assertDontSee('Neue Folge');
});

test('guest does not see edit delete buttons on show', function (): void {
    $episode = $this->createEpisodeWithRoles();
    $this->get(route('hoerbuecher.show', $episode))->assertOk()->assertDontSee('Bearbeiten')->assertDontSee('Löschen');
});

test('guest does not see previous speaker hint', function (): void {
    [$episode, $speaker] = $this->createEpisodeWithPreviousSpeaker();
    $this->get(route('hoerbuecher.show', $episode))->assertOk()->assertDontSee('Bisheriger Sprecher')->assertDontSee($speaker->name);
});

test('regular member does not see previous speaker hint', function (): void {
    [$episode, $speaker] = $this->createEpisodeWithPreviousSpeaker();
    $this->actingMember('Mitglied');
    $this->get(route('hoerbuecher.show', $episode))->assertOk()->assertDontSee('Bisheriger Sprecher');
});

test('authorized user sees previous speaker hint', function (): void {
    [$episode, $speaker] = $this->createEpisodeWithPreviousSpeaker();
    $this->actingMember('Admin');
    $this->get(route('hoerbuecher.show', $episode))->assertOk()->assertSee('Bisheriger Sprecher: '.$speaker->name);
});

test('guest cannot access create', function (): void {
    $this->get(route('hoerbuecher.create'))->assertRedirect(route('login'));
});

test('guest cannot access edit', function (): void {
    $episode = $this->createEpisodeWithRoles();
    $this->get(route('hoerbuecher.edit', $episode))->assertRedirect(route('login'));
});

test('guest cannot toggle role uploaded', function (): void {
    $episode = $this->createEpisodeWithRoles();
    $role = $episode->roles->first();
    $this->patch(route('hoerbuecher.roles.uploaded', $role), ['uploaded' => true])->assertRedirect(route('login'));
});

test('guest cannot access previous speaker endpoint', function (): void {
    $this->get(route('hoerbuecher.previous-speaker', ['name' => 'Matthew Drax']))->assertRedirect(route('login'));
});

test('regular member cannot create episode', function (): void {
    $this->actingMember('Mitglied');
    $this->get(route('hoerbuecher.create'))->assertForbidden();
});

test('regular member cannot edit episode', function (): void {
    $episode = $this->createEpisodeWithRoles();
    $this->actingMember('Mitglied');
    $this->get(route('hoerbuecher.edit', $episode))->assertForbidden();
});

test('admin sees create button on index', function (): void {
    $this->createEpisodeWithRoles();
    $this->actingMember('Admin');
    $this->get(route('hoerbuecher.index'))->assertOk()->assertSee(route('hoerbuecher.create'))->assertSee('Neue Folge');
});

test('vorstand sees create button on index', function (): void {
    $this->createEpisodeWithRoles();
    $this->actingMember('Vorstand');
    $this->get(route('hoerbuecher.index'))->assertOk()->assertSee('Neue Folge');
});

test('ag leader sees edit delete on show', function (): void {
    $episode = $this->createEpisodeWithRoles();
    $leader = $this->actingMember();
    $this->createAgFanhoerbuchTeam($leader);
    $this->get(route('hoerbuecher.show', $episode))->assertOk()->assertSee('Bearbeiten');
});

test('admin sees edit delete on show', function (): void {
    $episode = $this->createEpisodeWithRoles();
    $this->actingMember('Admin');
    $this->get(route('hoerbuecher.show', $episode))->assertOk()->assertSee('Bearbeiten');
});

test('regular member does not see create button on index', function (): void {
    $this->createEpisodeWithRoles();
    $this->actingMember('Mitglied');
    $this->get(route('hoerbuecher.index'))->assertOk()->assertDontSee(route('hoerbuecher.create'))->assertDontSee('Neue Folge');
});

test('regular member does not see edit delete on show', function (): void {
    $episode = $this->createEpisodeWithRoles();
    $this->actingMember('Mitglied');
    $this->get(route('hoerbuecher.show', $episode))->assertOk()->assertDontSee('Bearbeiten');
});

test('index has noindex meta tag', function (): void {
    $response = $this->get(route('hoerbuecher.index'));
    $response->assertOk();
    $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});

test('show has noindex meta tag', function (): void {
    $episode = $this->createEpisodeWithRoles();
    $response = $this->get(route('hoerbuecher.show', $episode));
    $response->assertOk();
    $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});

test('guest sees episode data on show', function (): void {
    $episode = $this->createEpisodeWithRoles();
    $response = $this->get(route('hoerbuecher.show', $episode));
    $response->assertOk()->assertSee('Testfolge Öffentlich')->assertSee('Testautor')->assertSee('F1')->assertSee('42%')->assertSee('Matthew Drax')->assertSee('Aruula');
});

test('guest sees episode data on index', function (): void {
    $episode = $this->createEpisodeWithRoles();
    $response = $this->get(route('hoerbuecher.index'));
    $response->assertOk()->assertSee('Testfolge Öffentlich')->assertSee('F1')->assertSee('42%');
});

test('guest does not see eardrax in navigation', function (): void {
    $this->get(route('hoerbuecher.index'))->assertOk()->assertDontSee('EARDRAX Dashboard');
});

test('guest does not see notes on show', function (): void {
    $episode = $this->createEpisodeWithRoles();
    $this->get(route('hoerbuecher.show', $episode))->assertOk()->assertDontSee('Interne Anmerkung')->assertDontSee('Anmerkungen');
});

test('guest does not see notes on index', function (): void {
    $this->createEpisodeWithRoles();
    $this->get(route('hoerbuecher.index'))->assertOk()->assertDontSee('Interne Anmerkung')->assertDontSee('Bemerkungen');
});

test('regular member does not see notes on index', function (): void {
    $this->createEpisodeWithRoles();
    $this->actingMember('Mitglied');
    $this->get(route('hoerbuecher.index'))->assertOk()->assertDontSee('Interne Anmerkung')->assertDontSee('Bemerkungen');
});

test('vorstand sees notes on index', function (): void {
    $this->createEpisodeWithRoles();
    $this->actingMember('Vorstand');
    $this->get(route('hoerbuecher.index'))->assertOk()->assertSee('Bemerkungen')->assertSee('Interne Anmerkung');
});

test('ag member sees notes on index', function (): void {
    $this->createEpisodeWithRoles();
    $leader = $this->actingMember();
    $this->createAgFanhoerbuchTeam($leader);
    $this->get(route('hoerbuecher.index'))->assertOk()->assertSee('Bemerkungen')->assertSee('Interne Anmerkung');
});

test('guest does not see responsible person on show', function (): void {
    $episode = $this->createEpisodeWithRoles();
    $episode->load('responsible');
    $this->get(route('hoerbuecher.show', $episode))->assertOk()->assertDontSee('Verantwortlich');
});

test('vorstand sees notes on show', function (): void {
    $episode = $this->createEpisodeWithRoles();
    $this->actingMember('Vorstand');
    $this->get(route('hoerbuecher.show', $episode))->assertOk()->assertSee('Anmerkungen')->assertSee('Interne Anmerkung');
});

test('ag member sees notes on show', function (): void {
    $episode = $this->createEpisodeWithRoles();
    $leader = $this->actingMember();
    $this->createAgFanhoerbuchTeam($leader);
    $this->get(route('hoerbuecher.show', $episode))->assertOk()->assertSee('Anmerkungen')->assertSee('Interne Anmerkung');
});

test('regular member does not see notes on show', function (): void {
    $episode = $this->createEpisodeWithRoles();
    $this->actingMember('Mitglied');
    $this->get(route('hoerbuecher.show', $episode))->assertOk()->assertDontSee('Anmerkungen')->assertDontSee('Interne Anmerkung');
});

test('vorstand sees responsible person on show', function (): void {
    $episode = $this->createEpisodeWithRoles();
    $episode->load('responsible');
    $this->actingMember('Vorstand');
    $this->get(route('hoerbuecher.show', $episode))->assertOk()->assertSee('Verantwortlich')->assertSee($episode->responsible->name);
});

test('regular member does not see responsible person on show', function (): void {
    $episode = $this->createEpisodeWithRoles();
    $this->actingMember('Mitglied');
    $this->get(route('hoerbuecher.show', $episode))->assertOk()->assertDontSee('Verantwortlich');
});

test('previous speaker shown from earlier episode', function (): void {
    $speaker = User::factory()->create(['name' => 'Doppelter Sprecher']);
    $earlier = AudiobookEpisode::create(['episode_number' => 'F20', 'title' => 'Frühere Folge', 'author' => 'Autor', 'planned_release_date' => '2025', 'status' => 'Skripterstellung', 'progress' => 100, 'roles_total' => 1, 'roles_filled' => 1, 'notes' => null]);
    $earlier->roles()->create(['name' => 'Matthew Drax', 'takes' => 1, 'user_id' => $speaker->id]);
    $current = AudiobookEpisode::create(['episode_number' => 'F21', 'title' => 'Aktuelle Folge mit gleichem Sprecher', 'author' => 'Autor', 'planned_release_date' => '01.06.2026', 'status' => 'Aufnahmensammlung', 'progress' => 50, 'roles_total' => 1, 'roles_filled' => 1, 'notes' => null]);
    $current->roles()->create(['name' => 'Matthew Drax', 'takes' => 1, 'user_id' => $speaker->id]);
    $this->actingMember('Admin');
    // Bisheriger Sprecher stammt aus der früheren Episode (aktuelle wird ausgeschlossen).
    // Da der gleiche Sprecher auch in einer früheren Episode besetzt war, wird der Hinweis angezeigt.
    $this->get(route('hoerbuecher.show', $current))->assertOk()->assertSee('Bisheriger Sprecher: Doppelter Sprecher');
});

test('previous speaker not shown when only in current episode', function (): void {
    $speaker = User::factory()->create(['name' => 'Nur-Aktuell Sprecher']);
    $current = AudiobookEpisode::create(['episode_number' => 'F22', 'title' => 'Folge ohne Vorgänger', 'author' => 'Autor', 'planned_release_date' => '01.06.2026', 'status' => 'Aufnahmensammlung', 'progress' => 50, 'roles_total' => 1, 'roles_filled' => 1, 'notes' => null]);
    $current->roles()->create(['name' => 'Neue Rolle', 'takes' => 1, 'user_id' => $speaker->id]);
    $this->actingMember('Admin');
    // Kein bisheriger Sprecher, da die Rolle nur in der aktuellen Episode existiert
    $this->get(route('hoerbuecher.show', $current))->assertOk()->assertDontSee('Bisheriger Sprecher');
});

test('robots txt does not disallow hoerbuecher', function (): void {
    $robotsTxt = file_get_contents(public_path('robots.txt'));
    expect($robotsTxt)->not->toContain('Disallow: /hoerbuecher');
});
