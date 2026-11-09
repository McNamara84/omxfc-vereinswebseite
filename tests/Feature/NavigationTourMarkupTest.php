<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\TourRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('member navigation renders tour runner and navigation anchors', function (): void {
    $team = Team::membersTeam();
    $member = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($member, ['role' => Role::Mitglied->value]);
    $response = $this->actingAs($member)->get(route('dashboard'))->assertOk()->assertSee('id="tour-runner-root"', false)->assertSee('id="tour-runner-skip"', false)->assertSee('id="tour-runner-back"', false)->assertSee('id="tour-runner-next"', false)->assertSee('id="tour-runner-complete"', false)->assertSee('data-tour-key="dashboard"', false)->assertSee('data-tour-key="section-community"', false)->assertSee('data-tour-key="profile-menu"', false)->assertSee('data-tour-key="profile-settings"', false)->assertSee('data-tour-key="mobile-menu-toggle"', false);
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('nav summary button'))->toHaveCount(0)
        ->and($crawler->filter('nav summary [data-tour-key][aria-expanded]'))->toHaveCount(0)
        ->and($crawler->filter('[data-tour-key="section-community"] > details > summary'))->toHaveCount(1)
        ->and($crawler->filter('[data-tour-key="section-community"]')->attr('x-bind:data-tour-open'))->toBe('show ? \'true\' : \'false\'');
});

test('main navigation tour uses version eight and shared sidebar selectors', function (): void {
    $tour = config('tours.hauptmenue');
    expect($tour['version'])->toBe(8)
        ->and($tour['description'])->toContain('Sidebar');
    foreach ($tour['steps'] as $step) {
        expect($step['selectors'] ?? [])->each->not->toContain('data-tour-device')->and($step['reveal'] ?? [])->each->each->not->toContain('data-tour-device');
    }
    $profileStep = collect($tour['steps'])->firstWhere('key', 'profile-settings');
    expect($profileStep['reveal']['mobile'] ?? null)->toBe(['[data-tour-key="profile-menu"]']);
});

test('bildersuche tour step matches its navigation anchor and feature flag', function (bool $enabled): void {
    config(['bildersuche.enabled' => $enabled]);
    $members = Team::membersTeam();
    $member = User::factory()->create(['current_team_id' => $members->id]);
    $members->users()->attach($member, ['role' => Role::Mitglied->value]);
    $response = $this->actingAs($member)->get(route('dashboard'))->assertOk();
    $crawler = new Crawler($response->getContent());
    $step = collect(config('tours.hauptmenue.steps'))->firstWhere('key', 'content-bildersuche');
    expect($step)->not->toBeNull()
        ->and(collect(app(TourRegistry::class)->definition('hauptmenue')->steps)->contains('key', 'content-bildersuche'))->toBe($enabled);
    $keys = array_column(config('tours.hauptmenue.steps'), 'key');
    expect(array_search('content-bildersuche', $keys, true))->toBe(array_search('content-kompendium', $keys, true) + 1);
    foreach (['desktop', 'mobile'] as $device) {
        $anchor = $crawler->filter($step['selectors'][$device]);
        expect($anchor)->toHaveCount($enabled ? 1 : 0);
        if ($enabled) {
            expect($anchor->attr('href'))->toBe(route('bildersuche.index'));
            foreach ($step['reveal'][$device] as $selector) {
                expect($crawler->filter($selector))->toHaveCount(1);
            }
        }
    }
})->with([[true], [false]]);

test('character tour anchor matches visible navigation and reveal targets', function (Role $role, bool $inRpgTeam, bool $visible): void {
    $members = Team::membersTeam();
    $user = User::factory()->create(['current_team_id' => $members->id]);
    $members->users()->attach($user, ['role' => $role->value]);
    if ($inRpgTeam) {
        $rpg = Team::factory()->create(['name' => 'AG Rollenspiel', 'personal_team' => false]);
        $rpg->users()->attach($user, ['role' => Role::Mitglied->value]);
    }
    $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();
    $crawler = new Crawler($response->getContent());
    $steps = collect(config('tours.hauptmenue.steps'));
    $step = $steps->firstWhere('key', 'teams-characters');
    expect($step)->not->toBeNull()
        ->and($step['title'])->toBe('Meine Charaktere');
    $keys = $steps->pluck('key')->all();
    expect(array_search('teams-characters', $keys, true))->toBe(array_search('teams-char-editor', $keys, true) + 1);
    foreach (['desktop', 'mobile'] as $device) {
        $anchor = $crawler->filter($step['selectors'][$device]);
        expect($anchor)->toHaveCount($visible ? 1 : 0);
        if ($visible) {
            expect($anchor->attr('href'))->toBe(route('rpg.characters.index'))
                ->and(trim($anchor->text()))->toBe('Meine Charaktere');
            foreach ($step['reveal'][$device] as $selector) {
                expect($crawler->filter($selector))->toHaveCount(1);
            }
        }
    }
    expect($step['reveal']['desktop'])->toBe(['[data-tour-key="section-projects"]'])
        ->and($step['reveal']['mobile'])->toBe(['[data-tour-key="mobile-menu-toggle"]', '[data-tour-key="section-projects"]']);
})->with([[Role::Mitglied, false, false], [Role::Mitglied, true, true], [Role::Admin, false, true]]);
