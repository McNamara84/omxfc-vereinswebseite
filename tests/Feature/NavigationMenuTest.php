<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use App\Support\Navigation\NavigationBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\DomCrawler\Crawler;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class NavigationMenuTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    private function createManagementUserWithDifferentCurrentTeam(Role $role): User
    {
        $managementTeam = Team::membersTeam() ?? Team::factory()->create([
            'name' => 'Mitglieder',
            'personal_team' => false,
        ]);

        $user = User::factory()->create(['current_team_id' => $managementTeam->id]);
        $managementTeam->users()->attach($user, ['role' => $role->value]);

        $otherTeam = Team::factory()->create([
            'user_id' => $user->id,
            'name' => 'Nebenverein',
            'personal_team' => false,
        ]);
        $otherTeam->users()->attach($user, ['role' => Role::Mitglied->value]);

        $user->forceFill(['current_team_id' => $otherTeam->id])->save();

        return $user->refresh();
    }

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

    public function test_guest_navigation_renders_without_polls_table(): void
    {
        Schema::drop('polls');

        $this->get(route('home'))
            ->assertOk()
            ->assertSeeText('Mitglied werden')
            ->assertDontSeeText('Dashboard');
    }

    public function test_guest_navigation_ignores_active_polls_with_invalid_legacy_visibility_values(): void
    {
        $user = User::factory()->create();

        DB::table('polls')->insert([
            'question' => 'Legacy-Umfrage',
            'menu_label' => 'Legacy-Umfrage',
            'visibility' => 'legacy-public',
            'status' => 'active',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'activated_at' => now()->subMinute(),
            'archived_at' => null,
            'created_by_user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::forget('polls.active_for_menu.v1');
        Cache::forget('polls.active_for_menu.v2');

        $this->get(route('home'))
            ->assertOk()
            ->assertSeeText('Mitglied werden')
            ->assertDontSeeText('Legacy-Umfrage');
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
        $response->assertSee(route('admin.touren.index'));
        $response->assertSeeText('Touren');
        $response->assertSeeText('Umfrage verwalten');
    }

    #[TestWith([Role::Admin->value])]
    #[TestWith([Role::Vorstand->value])]
    public function test_management_user_with_other_active_team_still_sees_event_management_link_in_navigation(string $roleValue): void
    {
        $user = $this->createManagementUserWithDifferentCurrentTeam(Role::from($roleValue));

        $response = $this->actingAs($user)->get(route('dashboard'));
        $crawler = new Crawler($response->getContent());
        $navigationText = preg_replace('/\s+/u', ' ', $crawler->filter('nav[aria-label="Hauptnavigation"]')->text());
        $navigationHtml = $crawler->filter('nav[aria-label="Hauptnavigation"]')->html();

        $response->assertOk();
        $this->assertIsString($navigationText);
        $this->assertIsString($navigationHtml);
        $this->assertStringContainsString('Vorstand', $navigationText);
        $this->assertStringContainsString(route('admin.veranstaltungen.index'), $navigationHtml);
        $this->assertStringNotContainsString(route('admin.auktionen.index'), $navigationHtml);
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
        $response->assertSee('Newsletter versenden');
    }

    public function test_newsletter_link_is_listed_under_vorstand_for_admin_and_vorstand_only(): void
    {
        $builder = app(NavigationBuilder::class);
        $admin = $this->createUserWithRole(Role::Admin)->load('teams', 'ownedTeams');
        $vorstand = $this->createUserWithRole(Role::Vorstand)->load('teams', 'ownedTeams');
        $kassenwart = $this->createUserWithRole(Role::Kassenwart)->load('teams', 'ownedTeams');

        $adminNavigation = $builder->build($admin);
        $vorstandNavigation = $builder->build($vorstand);
        $kassenwartNavigation = $builder->build($kassenwart);

        $this->assertContains('Newsletter versenden', $this->sectionItemTitles($adminNavigation, 'Vorstand'));
        $this->assertContains('Newsletter versenden', $this->sectionItemTitles($vorstandNavigation, 'Vorstand'));
        $this->assertNotContains('Newsletter versenden', $this->sectionItemTitles($kassenwartNavigation, 'Vorstand'));
        $this->assertNotContains('Newsletter versenden', $this->sectionItemTitles($adminNavigation, 'Admin'));
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

    /**
     * @param  array<string, mixed>  $navigation
     * @return array<int, string>
     */
    private function sectionItemTitles(array $navigation, string $sectionTitle): array
    {
        $section = collect($navigation['sections'] ?? [])->firstWhere('title', $sectionTitle);

        return array_column($section['items'] ?? [], 'title');
    }
}
