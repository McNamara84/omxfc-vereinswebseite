<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use App\Support\Navigation\NavigationBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\DomCrawler\Crawler;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class NavigationMenuTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    public function test_guest_navigation_uses_public_sections_and_featured_actions(): void
    {
        $response = $this->get(route('home'));
        $crawler = new Crawler($response->getContent());
        $featuredText = preg_replace('/\s+/u', ' ', $crawler->filter('[data-testid="nav-featured-links"]')->text());

        $response->assertOk();
        $this->assertIsString($featuredText);
        $this->assertStringContainsString('Aktuelle Veranstaltung', $featuredText);
        $this->assertStringContainsString('Mitglied werden', $featuredText);
        $response->assertSeeText('Verein');
        $response->assertSeeText('Veranstaltungen');
        $response->assertSeeText('Mitmachen');
        $response->assertSeeText('Login');
        $response->assertDontSeeText('Dashboard');
        $response->assertDontSeeText('Vorstand');
        $response->assertDontSeeText('Admin');
    }

    public function test_member_navigation_shows_reorganized_sections_without_governance_links(): void
    {
        $member = $this->createUserWithRole(Role::Mitglied);

        $response = $this->actingAs($member)->get(route('dashboard'));
        $crawler = new Crawler($response->getContent());
        $navigationText = preg_replace('/\s+/u', ' ', $crawler->filter('nav[aria-label="Hauptnavigation"]')->text());

        $response->assertOk();
        $response->assertSeeText('Dashboard');
        $response->assertSeeText('Community');
        $response->assertSeeText('Inhalte');
        $response->assertSeeText('Veranstaltungen');
        $response->assertSeeText('Verein');
        $response->assertSeeText('Baxx');
        $this->assertIsString($navigationText);
        $this->assertStringContainsString('Baxx verdienen', $navigationText);
        $this->assertStringContainsString('Belohnungen einlösen', $navigationText);
        $this->assertStringContainsString('Auktionen', $navigationText);
        $this->assertStringNotContainsString('Challenges', $navigationText);
        $response->assertDontSeeText('Vorstand');
        $response->assertDontSeeText('Admin');
        $response->assertSee(route('auktionen.index'));
    }

    public function test_admin_navigation_shows_governance_sections(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSeeText('Community');
        $response->assertSeeText('Teams & AG');
        $response->assertSeeText('Vorstand');
        $response->assertSeeText('Admin');
        $response->assertSee(route('admin.auktionen.index'));
        $response->assertSeeText('Umfrage verwalten');
    }

    public function test_navigation_builder_loads_team_visibility_state_only_once_per_build(): void
    {
        $user = $this->createUserWithRole(Role::Mitglied);
        $audioTeam = Team::factory()->create([
            'user_id' => $user->id,
            'name' => 'AG Fanhörbücher',
            'personal_team' => false,
        ]);
        $audioTeam->users()->attach($user, ['role' => Role::Mitglied->value]);

        $kompendiumTeam = Team::factory()->create([
            'user_id' => $user->id,
            'name' => 'AG Maddraxikon',
            'personal_team' => false,
        ]);
        $kompendiumTeam->users()->attach($user, ['role' => Role::Mitglied->value]);

        $user = $user->fresh();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $navigation = app(NavigationBuilder::class)->build($user);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $teamsSection = collect($navigation['sections'])->firstWhere('title', 'Teams & AG');

        $this->assertNotNull($teamsSection);
        $this->assertSame(['EARDRAX Dashboard', 'Kompendium', 'AG verwalten'], array_column($teamsSection['items'], 'title'));
        $this->assertLessThanOrEqual(2, count($queries));
    }

    public function test_authenticated_users_see_termine_link_in_veranstaltungen_menu(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->withoutVite()->actingAs($user)->get('/');

        $response->assertSee(route('termine'));
    }

    public function test_authenticated_navigation_uses_adaptive_desktop_dropdown_widths(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->withoutVite()->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertSee('data-testid="desktop-nav-dropdown-item"', false);
        $response->assertDontSee('data-testid="desktop-nav-dropdown-panel"', false);
        $response->assertSee('min-w-[14rem]', false);
        $response->assertSee('max-w-[min(24rem,calc(100vw-2rem))]', false);
        $response->assertSee('whitespace-nowrap', false);
    }

    public function test_navigation_menus_do_not_render_forms_as_direct_menu_children(): void
    {
        $user = $this->createUserWithRole(Role::Mitglied);

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();

        $crawler = new Crawler($response->getContent());

        $this->assertCount(0, $crawler->filter('ul.menu > form'));
    }

    public function test_authenticated_users_see_satzung_between_protokolle_and_kassenstand(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->get('/');

        // maryUI wraps menu items differently - check that all items exist in order
        $response->assertSeeInOrder([
            'Protokolle',
            'Satzung',
            'Kassenstand',
        ], false);
    }

    public function test_guests_see_termine_link_in_navigation(): void
    {
        $response = $this->withoutVite()->get('/');

        $response->assertSee(route('termine'));
    }

    public function test_mobile_menu_button_has_static_accessibility_text_before_alpine_initialization(): void
    {
        $response = $this->withoutVite()->get('/');

        $response->assertOk();

        $crawler = new Crawler($response->getContent());
        $menuToggle = $crawler->filter('button[aria-controls="mobile-navigation"]');
        $menuToggleAccessibilityText = $crawler->filter('button[aria-controls="mobile-navigation"] .sr-only');

        $this->assertCount(1, $menuToggle, 'Mobile menu toggle missing');
        $this->assertCount(1, $menuToggleAccessibilityText, 'Mobile menu toggle accessibility text missing');
        $this->assertSame('false', $menuToggle->attr('aria-expanded'));
        $this->assertSame('mobile-navigation', $menuToggle->attr('aria-controls'));
        $this->assertSame('Menü öffnen', trim($menuToggleAccessibilityText->text()));
    }

    public function test_admin_users_see_admin_menu_with_statistik_link(): void
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => Role::Admin->value]);

        $response = $this->actingAs($user)->get('/');

        $response->assertSee(route('admin.statistiken.index'));
        // maryUI menu-sub generates a summary element with "Admin" text
        $response->assertSee('Newsletter versenden');
    }

    public function test_non_admin_users_do_not_see_admin_menu(): void
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => Role::Mitglied->value]);

        $response = $this->actingAs($user)->get('/');

        $response->assertDontSee(route('admin.statistiken.index'));
        // Admin menu should not appear in navigation for non-admin users
        $response->assertDontSee('Newsletter versenden');
    }

    public function test_admin_users_do_not_see_hoerbuch_create_link_in_navigation_menu(): void
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => Role::Admin->value]);

        $response = $this->actingAs($user)->get('/');
        $response->assertDontSee(route('hoerbuecher.create'));
    }

    public function test_non_admin_users_do_not_see_hoerbuch_create_link(): void
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => Role::Mitglied->value]);

        $response = $this->actingAs($user)->get('/');

        $response->assertDontSee(route('hoerbuecher.create'));
    }

    public function test_authenticated_users_see_current_event_link_in_navigation(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertSee('/veranstaltungen/aktuell', false);
        $response->assertSee('Aktuelle Veranstaltung');
    }

    public function test_guests_see_current_event_link_in_navigation(): void
    {
        $response = $this->withoutVite()->get('/');

        $response->assertSee('/veranstaltungen/aktuell', false);
        $response->assertSee('Aktuelle Veranstaltung');
    }
}
