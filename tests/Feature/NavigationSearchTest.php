<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use App\Support\Navigation\NavigationBuilder;
use App\Support\Navigation\NavigationContext;
use App\Support\Navigation\NavigationSearch;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportDisablingBackButtonCache\SupportDisablingBackButtonCache;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(NavigationSearchTestFixtures::class);

test('navigation builder rejects unknown scopes and denies member tree to guests', function (): void {
    $builder = app(NavigationBuilder::class);
    expect($builder->build(null, [], 'member'))->toBe(['featured' => [], 'sections' => [], 'direct' => []])
        ->and(fn () => $builder->build(null, [], 'admin'))->toThrow(\InvalidArgumentException::class);
});

test('dynamic labels empty sections and nested visibility cannot expose hidden targets', function (): void {
    config(['navigation.guest' => [['layout' => 'featured', 'title' => 'Nur angemeldet', 'route' => 'chronik', 'requires_auth' => true], ['layout' => 'featured', 'title' => 'Nur Gäste', 'route' => 'spenden', 'requires_guest' => true], ['layout' => 'featured', 'title' => 'Dynamisch', 'route' => 'umfrage.aktuell', 'label_context_key' => 'missingLabel'], ['title' => 'Leerer Bereich', 'items' => [['title' => 'Geschützt', 'route' => 'reviews.index', 'requires_auth' => true]]], ['layout' => 'featured', 'title' => 'AG', 'route' => 'ag.index', 'visible_all' => [['has_non_personal_team' => true], ['requires_auth' => true]]]]]);
    $builder = app(NavigationBuilder::class);
    $guest = $builder->build(null, [], 'public');
    expect(array_column($guest['featured'], 'title'))->toBe(['Nur Gäste'])
        ->and($guest['sections'])->toBe([]);
    $member = $builder->build($this->createUserWithRole(Role::Mitglied), [], 'public');
    expect(array_column($member['featured'], 'title'))->not->toContain('Nur Gäste')
        ->and(array_column($member['featured'], 'title'))->not->toContain('Dynamisch')
        ->and(array_column($member['featured'], 'title'))->toContain('Nur angemeldet');
});

test('members find existing and new names', function (string $query, string $title): void {
    $this->actingMember();
    $this->getJson(route('mary.spotlight', ['search' => $query]))->assertOk()->assertJsonPath('0.name', $title);
})->with([['Baxx verdienen', 'Challenges &amp; Baxx'], ['AUFGABEN', 'Challenges &amp; Baxx'], ['  Challenges   Baxx ', 'Challenges &amp; Baxx'], ['Zoom', 'Online-Treffen'], ['rezensionen', 'Rezensionen']]);

test('empty short or unknown queries have no results', function (string $query): void {
    $this->getJson(route('mary.spotlight', ['search' => $query]))->assertOk()->assertExactJson([]);
})->with([[''], ['a'], ['   '], ['nichtvorhandene-funktion']]);

test('invalid search input is rejected', function (array $query): void {
    $this->getJson(route('mary.spotlight', $query))->assertStatus(422);
})->with([[['search' => ['Datenbank']]], [['search' => 'xx', 'scope' => 'admin']]]);

test('overlong query is rejected without redirect', function (bool $disableBackButtonCache): void {
    SupportDisablingBackButtonCache::$disableBackButtonCache = $disableBackButtonCache;
    try {
        $response = $this->getJson(route('mary.spotlight', ['search' => str_repeat('a', 101)]))->assertStatus(422);
        $this->assertPrivateSearchHeaders($response->headers);
    } finally {
        SupportDisablingBackButtonCache::$disableBackButtonCache = false;
    }
})->with([[false], [true]]);

test('direct guest requests cannot select members or admin scope', function (): void {
    foreach (['Datenbank', 'Challenges', 'RPG', 'Profil'] as $query) {
        $this->getJson(route('mary.spotlight', ['search' => $query, 'scope' => 'member', 'role' => 'admin']))->assertOk()->assertExactJson([]);
    }
    $this->getJson(route('mary.spotlight', ['search' => 'Chronik']))->assertOk()->assertJsonPath('0.name', 'Chronik');
});

test('member cannot find admin or rpg targets', function (bool $verified): void {
    config(['database-maintenance.enabled' => true]);
    $this->actingMember(attributes: ['email_verified_at' => $verified ? now() : null]);
    foreach (['Datenbank', 'RPG', 'Kassenbuch', 'EARDRAX'] as $query) {
        $this->getJson(route('mary.spotlight', ['search' => $query, 'scope' => 'member', 'role' => 'admin']))->assertOk()->assertExactJson([]);
    }
})->with([[true], [false]]);

test('applicants only search public targets', function (bool $verified): void {
    $user = $this->createUserWithRole(Role::Anwaerter);
    if (! $verified) {
        $user->forceFill(['email_verified_at' => null])->save();
    }
    $this->actingAs($user)->getJson(route('mary.spotlight', ['search' => 'Challenges', 'scope' => 'member']))->assertOk()->assertExactJson([]);
    $this->get('/satzung')->assertOk()->assertDontSee('data-testid="member-site-switch"', false);
})->with([[true], [false]]);

test('members without email confirmation find member targets', function (Role $role): void {
    $this->actingMember($role, ['email_verified_at' => null]);
    $this->getJson(route('mary.spotlight', ['search' => 'Challenges', 'scope' => 'member']))->assertOk()->assertJsonPath('0.link', route('todos.index'));
    $this->getJson(route('mary.spotlight', ['search' => 'Profil']))->assertOk()->assertJsonPath('0.link', route('profile.show'));
})->with([[Role::Mitglied], [Role::Admin]]);

test('missing current team membership or gate denial prevents member search', function (): void {
    $user = $this->createUserWithRole(Role::Mitglied);
    $user->setRelation('currentTeam', null);
    expect(app(NavigationContext::class)->canAccessMembers($user))->toBeFalse();
    $other = User::factory()->withPersonalTeam()->create();
    expect(app(NavigationContext::class)->canAccessMembers($other))->toBeFalse();
    $member = $this->actingMember(attributes: ['email_verified_at' => null]);
    $this->getJson(route('mary.spotlight', ['search' => 'Challenges']))->assertOk()->assertJsonCount(1);
    Gate::define('access-dashboard', fn () => false);
    $this->actingAs($member)->getJson(route('mary.spotlight', ['search' => 'Challenges']))->assertExactJson([]);
});

test('admin search uses existing role rules', function (string $role, bool $database, bool $verified): void {
    config(['database-maintenance.enabled' => true]);
    $this->actingMember(Role::from($role), ['email_verified_at' => $verified ? now() : null]);
    $response = $this->getJson(route('mary.spotlight', ['search' => 'Datenbank']))->assertOk();
    $database ? $response->assertJsonPath('0.link', route('admin.datenbank.index')) : $response->assertExactJson([]);
    $this->getJson(route('mary.spotlight', ['search' => 'Auktionen']))->assertOk()->assertJsonCount(2);
})->with([[Role::Admin->value, true, true], [Role::Admin->value, true, false], [Role::Vorstand->value, false, true], [Role::Vorstand->value, false, false], [Role::Kassenwart->value, false, true], [Role::Kassenwart->value, false, false]]);

test('search rechecks role after a change and has private cache headers', function (): void {
    config(['database-maintenance.enabled' => true]);
    $user = $this->actingMember(Role::Admin);
    $url = route('mary.spotlight', ['search' => 'Datenbank']);
    $response = $this->getJson($url)->assertJsonCount(1);
    $this->assertPrivateSearchHeaders($response->headers);
    Team::membersTeam()->users()->updateExistingPivot($user->id, ['role' => Role::Mitglied->value]);
    $this->actingAs($user->fresh())->getJson($url)->assertExactJson([]);
});

test('audio umlauts rpg and duplicate compendium follow ag memberships', function (): void {
    $user = $this->actingMember();
    foreach (['AG Rollenspiel', 'AG Fanhörbücher', 'AG Maddraxikon'] as $name) {
        $team = Team::factory()->create(['name' => $name, 'personal_team' => false]);
        $team->users()->attach($user, ['role' => Role::Mitglied->value]);
    }
    $this->actingAs($user->fresh());
    foreach (['Hörbücher', 'Hoerbuecher', 'EARDRAX'] as $query) {
        $this->getJson(route('mary.spotlight', ['search' => $query]))->assertOk()->assertJsonPath('0.link', route('hoerbuecher.index'));
    }
    $this->getJson(route('mary.spotlight', ['search' => 'RPG']))->assertOk()->assertJsonCount(2);
    $this->getJson(route('mary.spotlight', ['search' => 'Kompendium']))->assertOk()->assertJsonCount(1);
    $this->getJson(route('mary.spotlight', ['search' => 'Online Treffen']))->assertOk()->assertJsonPath('0.name', 'Online-Treffen');
});

test('search obeys feature flags and limits and ranks results', function (): void {
    $user = $this->actingMember();
    config(['cover-ratings.enabled' => false]);
    $this->getJson(route('mary.spotlight', ['search' => 'Cover']))->assertExactJson([]);
    config(['cover-ratings.enabled' => true]);
    $this->getJson(route('mary.spotlight', ['search' => 'Cover']))->assertJsonPath('0.name', 'Cover-Bewertungen');
    $items = [];
    for ($i = 0; $i < 15; $i++) {
        $items[] = ['title' => 'Suchziel '.$i, 'route' => 'mitglied.werden', 'href' => url('/ziel/'.$i)];
    }
    $items[] = ['title' => 'Suchziel', 'route' => 'chronik'];
    config(['navigation.guest' => [['title' => 'Test', 'items' => $items]]]);
    $results = app(NavigationSearch::class)->search($user, 'Suchziel');
    expect($results)->toHaveCount(2);
    // Identical canonical route keys are deduplicated.
    expect($results[0]['name'])->toBe('Suchziel');
    foreach ($items as $i => &$item) {
        $item['route'] = 'fake-'.$i;
    }
    unset($item);
    config(['navigation.guest' => [['title' => 'Test', 'items' => $items]]]);
    $items[15]['href'] = route('chronik');
    config(['navigation.guest' => [['title' => 'Test', 'items' => $items]]]);
    expect(app(NavigationSearch::class)->search(null, 'Suchziel'))->toHaveCount(10);
});

test('internal poll stays out of public menu and guest search and labels are escaped', function (): void {
    $member = $this->createUserWithRole(Role::Mitglied);
    DB::table('polls')->insert(['question' => 'Sicherheitsumfrage', 'menu_label' => '<img src=x onerror=alert(1)> Sicherheitsumfrage', 'visibility' => 'internal', 'status' => 'active', 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'activated_at' => now()->subMinute(), 'archived_at' => null, 'created_by_user_id' => $member->id, 'created_at' => now(), 'updated_at' => now()]);
    Cache::forget('polls.active_for_menu.v2');
    $this->getJson(route('mary.spotlight', ['search' => 'Sicherheitsumfrage']))->assertExactJson([]);
    $this->actingAs($member)->get('/satzung')->assertDontSeeText('Sicherheitsumfrage');
    $this->getJson(route('mary.spotlight', ['search' => 'Sicherheitsumfrage']))->assertJsonCount(1)->assertJsonPath('0.name', '&lt;img src=x onerror=alert(1)&gt; Sicherheitsumfrage');
    DB::table('polls')->update(['visibility' => 'public']);
    Cache::forget('polls.active_for_menu.v2');
    $this->actingAs($member)->get('/satzung')->assertSeeText('Sicherheitsumfrage');
});

test('public and member shells have one search and correct context switches', function (bool $verified): void {
    $member = $this->actingMember(attributes: ['email_verified_at' => $verified ? now() : null]);
    $public = $this->get('/satzung')->assertOk();
    $crawler = new Crawler($public->getContent());
    expect($crawler->filter('#navigation-search-dialog'))->toHaveCount(1)
        ->and($crawler->filter('#main-content[tabindex="-1"]'))->toHaveCount(1)
        ->and($crawler->filter('[data-testid="member-site-switch"]'))->toHaveCount(1)
        ->and($crawler->filter('[data-testid="navigation-brand-link"]')->attr('href'))->toBe(route('home', ['ansicht' => 'oeffentlich']))
        ->and($crawler->filter('[data-tour-key="baxx-todos"]'))->toHaveCount(0)
        ->and($crawler->filter('#tour-runner-root'))->toHaveCount(0);
    $crawler = new Crawler($this->get('/dashboard')->assertOk()->getContent());
    expect($crawler->filter('#navigation-search-dialog'))->toHaveCount(1)
        ->and($crawler->filter('[data-testid="public-site-switch"]'))->toHaveCount(1)
        ->and(array_column(app(NavigationBuilder::class)->build($member)['sections'], 'title'))->toBe(['Community', 'Lesen & Entdecken', 'Termine & Treffen', 'Projekte & Mitmachen', 'Verein']);
    $this->get('/?ansicht=oeffentlich')->assertOk()->assertSee('data-testid="nav-sections"', false);
    $this->get('/')->assertRedirect(route('dashboard'));
    $this->get('/?ansicht=anderes')->assertRedirect(route('dashboard'));
    $this->get('/?ansicht[]=oeffentlich')->assertRedirect(route('dashboard'));
})->with([[true], [false]]);

test('subpage has only its own active location', function (string $name, string $uri, ?string $expected): void {
    $user = $this->createUserWithRole(Role::Admin);
    $request = Request::create($uri);
    $route = new Route('GET', $uri, fn () => null);
    $route->name($name);
    $request->setRouteResolver(fn () => $route);
    $this->app->instance('request', $request);
    $navigation = app(NavigationBuilder::class)->build($user);
    $items = collect($navigation['featured'])->concat(collect($navigation['sections'])->flatMap(fn ($section) => $section['items']));
    $active = $items->where('active', true)->values();
    if ($expected === null) {
        expect($active)->toHaveCount(0);
    } else {
        expect($active)->toHaveCount(1)
            ->and($active[0]['key'])->toBe($expected)
            ->and($active[0]['aria_current'])->toBe('location');
    }
})->with([['reviews.show', '/rezensionen/123', 'reviews.index'], ['reviews.edit', '/rezensionen/123/bearbeiten', 'reviews.index'], ['fanfiction.show', '/fanfiction/123', 'fanfiction.index'], ['todos.create', '/aufgaben/erstellen', 'todos.index'], ['3d-modelle.edit', '/3d-modelle/123/bearbeiten', '3d-modelle.index'], ['mitglieder.karte.locked', '/mitglieder/karte/gesperrt', 'mitglieder.karte'], ['rpg.characters.history', '/rpg/charaktere/123/historie', 'rpg.characters.index'], ['rewards.admin.maddraxikon', '/belohnungen/admin/maddraxikon', 'rewards.admin'], ['newsletter.archiv.admin.edit', '/admin/newsletter-archiv/123/bearbeiten', null]]);
