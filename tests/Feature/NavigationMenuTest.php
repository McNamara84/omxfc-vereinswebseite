<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\NavigationMenu;
use App\Models\Team;
use App\Models\User;
use App\Support\Navigation\NavigationBuilder;
use App\Support\Navigation\NavigationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(NavigationMenuTestFixtures::class);
dataset('NavigationMenuTest.memberSidebarCases', static function (): iterable {
    foreach ([Role::Mitglied, Role::Admin, Role::Vorstand, Role::Kassenwart, Role::Ehrenmitglied, Role::Mitwirkender] as $role) {
        foreach ([false, true] as $verified) {
            foreach (['dashboard', 'kompendium.index'] as $routeName) {
                yield $role->value.' / '.($verified ? 'confirmed' : 'unconfirmed').' / '.$routeName => [$role, $verified, $routeName];
            }
        }
    }
});
test('guest navigation uses public sections and featured actions', function (): void {
    $response = $this->get(route('home'));
    $crawler = new Crawler($response->getContent());
    $featuredText = preg_replace('/\s+/u', ' ', $crawler->filter('[data-testid="nav-featured-links"]')->text());
    $response->assertOk();
    expect($featuredText)->toBeString()
        ->toContain('Aktuelle Veranstaltung')
        ->toContain('Mitglied werden');
    $response->assertSeeText('Verein');
    $response->assertSeeText('Termine');
    $response->assertSeeText('Spenden');
    $response->assertSeeText('Login');
    $response->assertDontSeeText('Dashboard');
    $response->assertDontSeeText('Vorstand');
    $response->assertDontSeeText('Admin');
});
test('navigation component renders member sidebar variant', function (bool $verified): void {
    $member = $this->createUserWithRole(Role::Mitglied);
    $member->forceFill(['email_verified_at' => $verified ? now() : null])->save();
    Livewire::actingAs($member)->test(NavigationMenu::class, ['variant' => 'member-sidebar'])->assertSet('variant', 'member-sidebar')->assertSee('data-testid="member-sidebar-navigation"', false)->assertDontSee('aria-label="Hauptnavigation"', false)->assertSee('data-tour-key="dashboard"', false)->assertSee('data-tour-key="community-members"', false)->dispatch('refresh-navigation-menu')->assertSee('data-tour-key="dashboard"', false)->assertSee('data-tour-key="community-members"', false);
})->with([[true], [false]]);
test('member pages render role based sidebar with or without email confirmation', function (Role $role, bool $verified, string $routeName): void {
    $user = $this->createUserWithRole($role);
    $user->forceFill(['email_verified_at' => $verified ? now() : null])->save();
    $response = $this->actingAs($user)->get(route($routeName))->assertOk();
    $sidebar = (new Crawler($response->getContent()))->filter('[data-testid="member-sidebar-navigation"]');
    expect($sidebar)->toHaveCount(1);
    foreach (['dashboard', 'section-community', 'section-content', 'section-events', 'section-projects', 'section-club', 'community-members', 'content-kompendium'] as $key) {
        expect($sidebar->filter('[data-tour-key="'.$key.'"]'))->toHaveCount(1);
    }
    expect($sidebar->filter('a[data-tour-key="dashboard"]')->attr('href'))->toBe(route('dashboard'))
        ->and($sidebar->filter('a[data-tour-key="community-members"]')->attr('href'))->toBe(route('mitglieder.index'))
        ->and($sidebar->filter('[aria-current="page"]'))->toHaveCount(1)
        ->and($sidebar->filter('[aria-current="page"]')->attr('href'))->toBe(route($routeName));
    $governance = in_array($role, [Role::Admin, Role::Vorstand, Role::Kassenwart], true);
    expect($sidebar->filter('[data-tour-key="section-board"]'))->toHaveCount($governance ? 1 : 0)
        ->and($sidebar->filter('[data-tour-key="board-ledger"]'))->toHaveCount($governance ? 1 : 0)
        ->and($sidebar->filter('[data-tour-key="section-admin"]'))->toHaveCount($role === Role::Admin ? 1 : 0)
        ->and($sidebar->filter('[data-tour-key="admin-messages"]'))->toHaveCount($role === Role::Admin ? 1 : 0)
        ->and($user->fresh()->hasVerifiedEmail())->toBe($verified);
})->with('NavigationMenuTest.memberSidebarCases');
test('navigation component renders member profile variant', function (): void {
    $member = $this->createUserWithRole(Role::Mitglied);
    Livewire::actingAs($member)->test(NavigationMenu::class, ['variant' => 'member-profile'])->assertSet('variant', 'member-profile')->assertSee('data-testid="profile-menu-trigger"', false)->assertDontSee('data-testid="member-sidebar-navigation"', false);
});
test('navigation view renders guest profile placeholder', function (): void {
    $html = view('navigation-menu', ['variant' => 'member-profile'])->render();
    expect($html)->toContain('data-testid="profile-menu-guest-placeholder"');
});
test('navigation component falls back to public variant for unknown input', function (): void {
    Livewire::test(NavigationMenu::class, ['variant' => 'unknown'])->assertSet('variant', 'public-navbar')->assertSee('aria-label="Hauptnavigation"', false);
});
test('navigation variant cannot be changed by the client', function (): void {
    $component = Livewire::test(NavigationMenu::class, ['variant' => 'public-navbar']);
    expect(fn () => $component->set('variant', 'member-sidebar'))->toThrow(CannotUpdateLockedPropertyException::class);
});
test('guest navigation renders without polls table', function (): void {
    Schema::drop('polls');
    $this->get(route('home'))->assertOk()->assertSeeText('Mitglied werden')->assertDontSeeText('Dashboard');
});
test('guest navigation ignores active polls with invalid legacy visibility values', function (): void {
    $user = User::factory()->create();
    DB::table('polls')->insert(['question' => 'Legacy-Umfrage', 'menu_label' => 'Legacy-Umfrage', 'visibility' => 'legacy-public', 'status' => 'active', 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'activated_at' => now()->subMinute(), 'archived_at' => null, 'created_by_user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
    Cache::forget('polls.active_for_menu.v1');
    Cache::forget('polls.active_for_menu.v2');
    $this->get(route('home'))->assertOk()->assertSeeText('Mitglied werden')->assertDontSeeText('Legacy-Umfrage');
});
test('member navigation shows reorganized sections without governance links', function (): void {
    $member = $this->createUserWithRole(Role::Mitglied);
    $response = $this->actingAs($member)->get(route('dashboard'));
    $crawler = new Crawler($response->getContent());
    $navigationText = preg_replace('/\s+/u', ' ', $crawler->filter('[data-testid="member-sidebar-navigation"]')->text());
    $response->assertOk();
    $response->assertSeeText('Dashboard');
    $response->assertSeeText('Community');
    $response->assertSeeText('Lesen & Entdecken');
    $response->assertSeeText('Termine & Treffen');
    $response->assertSeeText('Verein');
    $response->assertSeeText('Baxx');
    expect($navigationText)->toBeString()
        ->toContain('Challenges & Baxx')
        ->toContain('Belohnungen einlösen')
        ->toContain('Auktionen')->not->toContain('Teams & AG')->not->toContain('Vorstand')->not->toContain('Admin');
    $response->assertSee(route('auktionen.index'));
});
test('admin navigation shows governance sections', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $response = $this->actingAs($admin)->get(route('dashboard'));
    $response->assertOk();
    $response->assertSeeText('Community');
    $response->assertSeeText('Projekte & Mitmachen');
    $response->assertSeeText('Vorstand');
    $response->assertSeeText('Admin');
    $response->assertSee(route('admin.auktionen.index'));
    $response->assertSee(route('admin.touren.index'));
    $response->assertSeeText('Touren verwalten');
    $response->assertSeeText('Umfragen verwalten');
});
test('management user with other active team still sees event management link in navigation', function (string $roleValue): void {
    $user = $this->createManagementUserWithDifferentCurrentTeam(Role::from($roleValue));
    $response = $this->actingAs($user)->get(route('dashboard'));
    $crawler = new Crawler($response->getContent());
    $navigationText = preg_replace('/\s+/u', ' ', $crawler->filter('[data-testid="member-sidebar-navigation"]')->text());
    $navigationHtml = $crawler->filter('[data-testid="member-sidebar-navigation"]')->html();
    $response->assertOk();
    expect($navigationText)->toBeString()
        ->and($navigationHtml)->toBeString()
        ->and($navigationText)->toContain('Vorstand')
        ->and($navigationHtml)->toContain(route('admin.veranstaltungen.index'))->not->toContain(route('admin.auktionen.index'));
})->with([[Role::Admin->value], [Role::Vorstand->value]]);
test('navigation builder has bounded queries with fresh transactional role reads', function (): void {
    $user = $this->createUserWithRole(Role::Mitglied);
    $audioTeam = Team::factory()->create(['user_id' => $user->id, 'name' => 'AG Fanhörbücher', 'personal_team' => false]);
    $audioTeam->users()->attach($user, ['role' => Role::Mitglied->value]);
    $kompendiumTeam = Team::factory()->create(['user_id' => $user->id, 'name' => 'AG Maddraxikon', 'personal_team' => false]);
    $kompendiumTeam->users()->attach($user, ['role' => Role::Mitglied->value]);
    $user = $user->fresh();
    app(NavigationContext::class)->canAccessMembers($user);
    DB::flushQueryLog();
    DB::enableQueryLog();
    $navigation = app(NavigationBuilder::class)->build($user);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    $teamsSection = collect($navigation['sections'])->firstWhere('title', 'Projekte & Mitmachen');
    expect($teamsSection)->not->toBeNull()
        ->and(array_column($teamsSection['items'], 'title'))->toBe(['Challenges & Baxx', 'Belohnungen einlösen', 'Fanhörbücher (EARDRAX)', 'AG verwalten']);
    // RefreshDatabase keeps this build in a transaction: the fresh role read
    // also clears relations, so authorization must reload its team data.
    expect(count($queries))->toBeLessThanOrEqual(6);
});
test('member sidebar entries have explicit icons', function (): void {
    $member = $this->createUserWithRole(Role::Mitglied)->load('teams', 'ownedTeams');
    $navigation = app(NavigationBuilder::class)->build($member);
    $items = collect($navigation['featured'])->concat(collect($navigation['sections'])->flatMap(fn (array $section): array => $section['items']))->all();
    expect($items)->not->toBeEmpty();
    foreach ($items as $item) {
        expect($item['icon'] ?? null)->toBeString()
            ->and($item['icon'])->not->toBeEmpty();
    }
    foreach (config('navigation.auth') as $entry) {
        expect($entry['icon'] ?? null)->toBeString();
        foreach ($entry['items'] ?? [] as $item) {
            expect($item['icon'] ?? null)->toBeString();
        }
    }
});
test('ag rollenspiel member sees character editor in teams section', function (): void {
    $user = $this->addAgRollenspielMembership($this->createUserWithRole(Role::Mitglied));
    $navigation = app(NavigationBuilder::class)->build($user);
    $teamsSection = collect($navigation['sections'])->firstWhere('title', 'Projekte & Mitmachen');
    expect($teamsSection)->not->toBeNull()
        ->and(array_column($teamsSection['items'], 'title'))->toContain('Charakter-Editor');
});
test('global admin with different current team sees character editor in teams section only', function (): void {
    $user = $this->createManagementUserWithDifferentCurrentTeam(Role::Admin);
    $navigation = app(NavigationBuilder::class)->build($user);
    $teamsSection = collect($navigation['sections'])->firstWhere('title', 'Projekte & Mitmachen');
    $adminSection = collect($navigation['sections'])->firstWhere('title', 'Admin');
    expect($teamsSection)->not->toBeNull()
        ->and(array_column($teamsSection['items'], 'title'))->toContain('Charakter-Editor')
        ->and($adminSection)->toBeNull();
});
test('authenticated users see termine link in veranstaltungen menu', function (): void {
    $user = User::factory()->withPersonalTeam()->create();
    $response = $this->withoutVite()->actingAs($user)->get('/');
    $response->assertSee(route('termine'));
});
test('authenticated navigation uses adaptive desktop dropdown widths', function (): void {
    $user = User::factory()->withPersonalTeam()->create();
    $response = $this->withoutVite()->actingAs($user)->get('/');
    $response->assertOk();
    $response->assertSee('data-testid="desktop-nav-dropdown-item"', false);
    $response->assertDontSee('data-testid="desktop-nav-dropdown-panel"', false);
    $response->assertSee('min-w-[14rem]', false);
    $response->assertSee('max-w-[min(24rem,calc(100vw-2rem))]', false);
    $response->assertSee('whitespace-nowrap', false);
});
test('navigation menus do not render forms as direct menu children', function (): void {
    $user = $this->createUserWithRole(Role::Mitglied);
    $response = $this->actingAs($user)->get('/dashboard');
    $response->assertOk();
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('ul.menu > form'))->toHaveCount(0);
});
test('public route keeps public navbar for authenticated member', function (): void {
    $member = $this->createUserWithRole(Role::Mitglied);
    $response = $this->actingAs($member)->get(route('satzung'));
    $crawler = new Crawler($response->getContent());
    $response->assertOk();
    expect($crawler->filter('nav[aria-label="Hauptnavigation"]'))->toHaveCount(1)
        ->and($crawler->filter('#member-drawer'))->toHaveCount(0)
        ->and($crawler->filter('[data-testid="member-sidebar-navigation"]'))->toHaveCount(0);
});
test('protected route uses single member shell navigation and theme controller', function (): void {
    $member = $this->createUserWithRole(Role::Mitglied);
    $response = $this->actingAs($member)->get(route('dashboard'));
    $crawler = new Crawler($response->getContent());
    $response->assertOk();
    $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    expect($crawler->filter('nav[aria-label="Kopfleiste des Mitgliederbereichs"]'))->toHaveCount(1)
        ->and($crawler->filter('input#member-drawer.drawer-toggle'))->toHaveCount(1)
        ->and($crawler->filter('[data-testid="member-sidebar-navigation"]'))->toHaveCount(1)
        ->and($crawler->filter('[data-testid="theme-toggle"]'))->toHaveCount(1)
        ->and($crawler->filter('input[id*="omxfc-theme-controller"]'))->toHaveCount(1);
    $response->assertSee('@omxfc-theme-sync.window="syncExternal($event.detail)"', false);
    expect($crawler->filter('[data-tour-key="dashboard"][aria-current="page"]'))->toHaveCount(1)
        ->and($crawler->filter('nav[aria-label="Hauptnavigation"]'))->toHaveCount(0);
    $sidebar = $crawler->filter('[data-testid="member-sidebar-navigation"]');
    $profileLink = $sidebar->filter('a[aria-label^="Profil von "]');
    $expectedProfileLabel = "Profil von {$member->nicknameOrName()} öffnen";
    expect($profileLink)->toHaveCount(1)
        ->and($profileLink->attr('aria-label'))->toBe("Profil von {$member->nicknameOrName()} Mitglied öffnen")
        ->and($profileLink->attr('title'))->toBe($expectedProfileLabel)
        ->and($sidebar->filter('ul.menu > hr'))->toHaveCount(0)
        ->and($sidebar->filter('ul.menu > li[role="separator"]')->count())->toBeGreaterThanOrEqual(2)
        ->and($sidebar->filter('ul.menu a:not([aria-label])'))->toHaveCount(0)
        ->and($sidebar->filter('summary:not([aria-label])'))->toHaveCount(0);
});
test('authenticated users see satzung between protokolle and kassenstand', function (): void {
    $user = $this->createUserWithRole(Role::Mitglied);
    $response = $this->actingAs($user)->get('/dashboard');
    // maryUI wraps menu items differently - check that all items exist in order
    $response->assertSeeInOrder(['Protokolle', 'Satzung', 'Kassenstand'], false);
});
test('guests see termine link in navigation', function (): void {
    $response = $this->withoutVite()->get('/');
    $response->assertSee(route('termine'));
});
test('mobile menu button has static accessibility text before alpine initialization', function (): void {
    $response = $this->withoutVite()->get('/');
    $response->assertOk();
    $crawler = new Crawler($response->getContent());
    $menuToggle = $crawler->filter('button[aria-controls="mobile-navigation"]');
    $menuToggleAccessibilityText = $crawler->filter('button[aria-controls="mobile-navigation"] .sr-only');
    expect($menuToggle)->toHaveCount(1)
        ->and($menuToggleAccessibilityText)->toHaveCount(1)
        ->and($menuToggle->attr('aria-expanded'))->toBe('false')
        ->and($menuToggle->attr('aria-controls'))->toBe('mobile-navigation')
        ->and(trim($menuToggleAccessibilityText->text()))->toBe('Menü öffnen');
});
test('authenticated profile menu uses an accessible mary dropdown trigger', function (): void {
    $user = User::factory()->withPersonalTeam()->create(['name' => 'Ada Beispiel', 'vorname' => 'Ada']);
    $response = $this->withoutVite()->actingAs($user)->get('/');
    $response->assertOk();
    $crawler = new Crawler($response->getContent());
    $trigger = $crawler->filter('nav details > summary[data-testid="profile-menu-trigger"]');
    expect($trigger)->toHaveCount(1)
        ->and($trigger->attr('aria-label'))->toBe('Profilmenü von Ada öffnen')
        ->and($trigger->attr('aria-haspopup'))->toBeNull()
        ->and($trigger->attr('aria-expanded'))->toBe('false')
        ->and($trigger->attr('x-bind:aria-expanded'))->toBe('open.toString()')
        ->and($trigger->attr('x-on:keydown.escape.window'))->toContain('open = false')
        ->and($trigger->filter('button, a, input, select, textarea'))->toHaveCount(0)
        ->and($crawler->filter('nav details > ul > div'))->toHaveCount(0)
        ->and($crawler->filter('nav details > ul > li')->count())->toBeGreaterThan(0);
});
test('member profile menu includes its visible name in the accessible name', function (): void {
    $this->actingMember(attributes: ['name' => 'Interner Kontoname', 'vorname' => 'Ada']);
    $response = $this->withoutVite()->get('/dashboard');
    $response->assertOk();
    $crawler = new Crawler($response->getContent());
    $trigger = $crawler->filter('summary[data-testid="profile-menu-trigger"]');
    expect($trigger)->toHaveCount(1)
        ->and(trim($trigger->filter('span')->text()))->toBe('Ada')
        ->and($trigger->attr('aria-label'))->toBe('Profilmenü von Ada öffnen');
});
test('admin users see admin menu with statistik link', function (): void {
    $team = Team::membersTeam();
    $user = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($user, ['role' => Role::Admin->value]);
    $response = $this->actingAs($user)->get('/dashboard');
    $response->assertSee(route('admin.statistiken.index'));
    $response->assertSee('Newsletter versenden');
});
test('newsletter link is listed under vorstand for admin and vorstand only', function (): void {
    $builder = app(NavigationBuilder::class);
    $admin = $this->createUserWithRole(Role::Admin)->load('teams', 'ownedTeams');
    $vorstand = $this->createUserWithRole(Role::Vorstand)->load('teams', 'ownedTeams');
    $kassenwart = $this->createUserWithRole(Role::Kassenwart)->load('teams', 'ownedTeams');
    $adminNavigation = $builder->build($admin);
    $vorstandNavigation = $builder->build($vorstand);
    $kassenwartNavigation = $builder->build($kassenwart);
    expect($this->sectionItemTitles($adminNavigation, 'Vorstand'))->toContain('Newsletter versenden')
        ->and($this->sectionItemTitles($vorstandNavigation, 'Vorstand'))->toContain('Newsletter versenden')
        ->and($this->sectionItemTitles($kassenwartNavigation, 'Vorstand'))->not->toContain('Newsletter versenden')
        ->and($this->sectionItemTitles($adminNavigation, 'Admin'))->not->toContain('Newsletter versenden');
});
test('non admin users do not see admin menu', function (): void {
    $team = Team::membersTeam();
    $user = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($user, ['role' => Role::Mitglied->value]);
    $response = $this->actingAs($user)->get('/dashboard');
    $response->assertDontSee(route('admin.statistiken.index'));
    // Admin menu should not appear in navigation for non-admin users
    $response->assertDontSee('Newsletter versenden');
});
test('cover ratings navigation link follows its feature flag', function (): void {
    $member = $this->createUserWithRole(Role::Mitglied)->load('teams', 'ownedTeams');
    $builder = app(NavigationBuilder::class);
    config(['cover-ratings.enabled' => false]);
    $disabledNavigation = $builder->build($member);
    expect($this->sectionItemTitles($disabledNavigation, 'Lesen & Entdecken'))->not->toContain('Cover-Bewertungen');
    $this->actingAs($member)->get(route('dashboard'))->assertDontSee('data-tour-key="community-cover-ratings"', false);
    config(['cover-ratings.enabled' => true]);
    $enabledNavigation = $builder->build($member);
    expect($this->sectionItemTitles($enabledNavigation, 'Lesen & Entdecken'))->toContain('Cover-Bewertungen');
    $coverRatingsItem = collect($enabledNavigation['sections'])->firstWhere('title', 'Lesen & Entdecken')['items'] ?? [];
    $coverRatingsItem = collect($coverRatingsItem)->firstWhere('title', 'Cover-Bewertungen');
    expect($coverRatingsItem['tour_key'] ?? null)->toBe('community-cover-ratings');
    $this->actingAs($member)->get(route('dashboard'))->assertSee('data-tour-key="community-cover-ratings"', false);
});
test('admin users do not see hoerbuch create link in navigation menu', function (): void {
    $team = Team::membersTeam();
    $user = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($user, ['role' => Role::Admin->value]);
    $response = $this->actingAs($user)->get('/dashboard');
    $response->assertDontSee(route('hoerbuecher.create'));
});
test('non admin users do not see hoerbuch create link', function (): void {
    $team = Team::membersTeam();
    $user = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($user, ['role' => Role::Mitglied->value]);
    $response = $this->actingAs($user)->get('/dashboard');
    $response->assertDontSee(route('hoerbuecher.create'));
});
test('authenticated users see current event link in navigation', function (): void {
    $user = User::factory()->withPersonalTeam()->create();
    $response = $this->actingAs($user)->get('/');
    $response->assertSee('/veranstaltungen/aktuell', false);
    $response->assertSee('Aktuelle Veranstaltung');
});
test('guests see current event link in navigation', function (): void {
    $response = $this->withoutVite()->get('/');
    $response->assertSee('/veranstaltungen/aktuell', false);
    $response->assertSee('Aktuelle Veranstaltung');
});
