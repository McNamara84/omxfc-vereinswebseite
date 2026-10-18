<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use App\Support\Navigation\NavigationBuilder;
use App\Support\Navigation\NavigationContext;
use App\Support\Navigation\NavigationSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\DomCrawler\Crawler;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class NavigationSearchTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    public function test_navigation_builder_rejects_unknown_scopes_and_denies_member_tree_to_guests(): void
    {
        $builder = app(NavigationBuilder::class);
        $this->assertSame(['featured' => [], 'sections' => [], 'direct' => []], $builder->build(null, [], 'member'));
        $this->expectException(\InvalidArgumentException::class);
        $builder->build(null, [], 'admin');
    }

    public function test_dynamic_labels_empty_sections_and_nested_visibility_cannot_expose_hidden_targets(): void
    {
        config(['navigation.guest' => [
            ['layout' => 'featured', 'title' => 'Nur angemeldet', 'route' => 'chronik', 'requires_auth' => true],
            ['layout' => 'featured', 'title' => 'Nur Gäste', 'route' => 'spenden', 'requires_guest' => true],
            ['layout' => 'featured', 'title' => 'Dynamisch', 'route' => 'umfrage.aktuell', 'label_context_key' => 'missingLabel'],
            ['title' => 'Leerer Bereich', 'items' => [
                ['title' => 'Geschützt', 'route' => 'reviews.index', 'requires_auth' => true],
            ]],
            ['layout' => 'featured', 'title' => 'AG', 'route' => 'ag.index', 'visible_all' => [
                ['has_non_personal_team' => true], ['requires_auth' => true],
            ]],
        ]]);
        $builder = app(NavigationBuilder::class);
        $guest = $builder->build(null, [], 'public');
        $this->assertSame(['Nur Gäste'], array_column($guest['featured'], 'title'));
        $this->assertSame([], $guest['sections']);
        $member = $builder->build($this->createUserWithRole(Role::Mitglied), [], 'public');
        $this->assertNotContains('Nur Gäste', array_column($member['featured'], 'title'));
        $this->assertNotContains('Dynamisch', array_column($member['featured'], 'title'));
        $this->assertContains('Nur angemeldet', array_column($member['featured'], 'title'));
    }

    #[TestWith(['Baxx verdienen', 'Challenges &amp; Baxx'])]
    #[TestWith(['AUFGABEN', 'Challenges &amp; Baxx'])]
    #[TestWith(['  Challenges   Baxx ', 'Challenges &amp; Baxx'])]
    #[TestWith(['Zoom', 'Online-Treffen'])]
    #[TestWith(['rezensionen', 'Rezensionen'])]
    public function test_members_find_existing_and_new_names(string $query, string $title): void
    {
        $this->actingMember();
        $this->getJson(route('mary.spotlight', ['search' => $query]))
            ->assertOk()->assertJsonPath('0.name', $title);
    }

    #[TestWith([''])]
    #[TestWith(['a'])]
    #[TestWith(['   '])]
    #[TestWith(['nichtvorhandene-funktion'])]
    public function test_empty_short_or_unknown_queries_have_no_results(string $query): void
    {
        $this->getJson(route('mary.spotlight', ['search' => $query]))->assertOk()->assertExactJson([]);
    }

    #[TestWith([['search' => ['Datenbank']]])]
    #[TestWith([['search' => 'xx', 'scope' => 'admin']])]
    public function test_invalid_search_input_is_rejected(array $query): void
    {
        $this->getJson(route('mary.spotlight', $query))->assertStatus(422);
    }

    public function test_overlong_query_is_rejected_without_redirect(): void
    {
        $this->getJson(route('mary.spotlight', ['search' => str_repeat('a', 101)]))
            ->assertStatus(422)->assertHeader('Cache-Control', 'max-age=0, no-store, private');
    }

    public function test_direct_guest_requests_cannot_select_members_or_admin_scope(): void
    {
        foreach (['Datenbank', 'Challenges', 'RPG', 'Profil'] as $query) {
            $this->getJson(route('mary.spotlight', ['search' => $query, 'scope' => 'member', 'role' => 'admin']))
                ->assertOk()->assertExactJson([]);
        }
        $this->getJson(route('mary.spotlight', ['search' => 'Chronik']))->assertOk()->assertJsonPath('0.name', 'Chronik');
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function test_member_cannot_find_admin_or_rpg_targets(bool $verified): void
    {
        config(['database-maintenance.enabled' => true]);
        $this->actingMember(attributes: ['email_verified_at' => $verified ? now() : null]);
        foreach (['Datenbank', 'RPG', 'Kassenbuch', 'EARDRAX'] as $query) {
            $this->getJson(route('mary.spotlight', ['search' => $query, 'scope' => 'member', 'role' => 'admin']))->assertOk()->assertExactJson([]);
        }
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function test_applicants_only_search_public_targets(bool $verified): void
    {
        $user = $this->createUserWithRole(Role::Anwaerter);
        if (! $verified) {
            $user->forceFill(['email_verified_at' => null])->save();
        }
        $this->actingAs($user)->getJson(route('mary.spotlight', ['search' => 'Challenges', 'scope' => 'member']))
            ->assertOk()->assertExactJson([]);
        $this->get('/satzung')->assertOk()->assertDontSee('data-testid="member-site-switch"', false);
    }

    #[TestWith([Role::Mitglied])]
    #[TestWith([Role::Admin])]
    public function test_members_without_email_confirmation_find_member_targets(Role $role): void
    {
        $this->actingMember($role, ['email_verified_at' => null]);

        $this->getJson(route('mary.spotlight', ['search' => 'Challenges', 'scope' => 'member']))
            ->assertOk()->assertJsonPath('0.link', route('todos.index'));
        $this->getJson(route('mary.spotlight', ['search' => 'Profil']))
            ->assertOk()->assertJsonPath('0.link', route('profile.show'));
    }

    public function test_missing_current_team_membership_or_gate_denial_prevents_member_search(): void
    {
        $user = $this->createUserWithRole(Role::Mitglied);
        $user->setRelation('currentTeam', null);
        $this->assertFalse(app(NavigationContext::class)->canAccessMembers($user));
        $other = User::factory()->withPersonalTeam()->create();
        $this->assertFalse(app(NavigationContext::class)->canAccessMembers($other));
        $member = $this->actingMember(attributes: ['email_verified_at' => null]);
        $this->getJson(route('mary.spotlight', ['search' => 'Challenges']))->assertOk()->assertJsonCount(1);
        Gate::define('access-dashboard', fn () => false);
        $this->actingAs($member)->getJson(route('mary.spotlight', ['search' => 'Challenges']))->assertExactJson([]);
    }

    #[TestWith([Role::Admin->value, true, true])]
    #[TestWith([Role::Admin->value, true, false])]
    #[TestWith([Role::Vorstand->value, false, true])]
    #[TestWith([Role::Vorstand->value, false, false])]
    #[TestWith([Role::Kassenwart->value, false, true])]
    #[TestWith([Role::Kassenwart->value, false, false])]
    public function test_admin_search_uses_existing_role_rules(string $role, bool $database, bool $verified): void
    {
        config(['database-maintenance.enabled' => true]);
        $this->actingMember(Role::from($role), ['email_verified_at' => $verified ? now() : null]);
        $response = $this->getJson(route('mary.spotlight', ['search' => 'Datenbank']))->assertOk();
        $database ? $response->assertJsonPath('0.link', route('admin.datenbank.index')) : $response->assertExactJson([]);
        $this->getJson(route('mary.spotlight', ['search' => 'Auktionen']))->assertOk()->assertJsonCount(2);
    }

    public function test_search_rechecks_role_after_a_change_and_has_private_cache_headers(): void
    {
        config(['database-maintenance.enabled' => true]);
        $user = $this->actingMember(Role::Admin);
        $url = route('mary.spotlight', ['search' => 'Datenbank']);
        $this->getJson($url)->assertJsonCount(1)->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        Team::membersTeam()->users()->updateExistingPivot($user->id, ['role' => Role::Mitglied->value]);
        $this->actingAs($user->fresh())->getJson($url)->assertExactJson([]);
    }

    public function test_audio_umlauts_rpg_and_duplicate_compendium_follow_ag_memberships(): void
    {
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
    }

    public function test_search_obeys_feature_flags_and_limits_and_ranks_results(): void
    {
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
        $this->assertCount(2, $results); // Identical canonical route keys are deduplicated.
        $this->assertSame('Suchziel', $results[0]['name']);
        foreach ($items as $i => &$item) {
            $item['route'] = 'fake-'.$i;
        }
        unset($item);
        config(['navigation.guest' => [['title' => 'Test', 'items' => $items]]]);
        $items[15]['href'] = route('chronik');
        config(['navigation.guest' => [['title' => 'Test', 'items' => $items]]]);
        $this->assertCount(10, app(NavigationSearch::class)->search(null, 'Suchziel'));
    }

    public function test_internal_poll_stays_out_of_public_menu_and_guest_search_and_labels_are_escaped(): void
    {
        $member = $this->createUserWithRole(Role::Mitglied);
        DB::table('polls')->insert([
            'question' => 'Sicherheitsumfrage', 'menu_label' => '<img src=x onerror=alert(1)> Sicherheitsumfrage',
            'visibility' => 'internal', 'status' => 'active', 'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(), 'activated_at' => now()->subMinute(), 'archived_at' => null,
            'created_by_user_id' => $member->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        Cache::forget('polls.active_for_menu.v2');
        $this->getJson(route('mary.spotlight', ['search' => 'Sicherheitsumfrage']))->assertExactJson([]);
        $this->actingAs($member)->get('/satzung')->assertDontSeeText('Sicherheitsumfrage');
        $this->getJson(route('mary.spotlight', ['search' => 'Sicherheitsumfrage']))
            ->assertJsonCount(1)->assertJsonPath('0.name', '&lt;img src=x onerror=alert(1)&gt; Sicherheitsumfrage');
        DB::table('polls')->update(['visibility' => 'public']);
        Cache::forget('polls.active_for_menu.v2');
        $this->actingAs($member)->get('/satzung')->assertSeeText('Sicherheitsumfrage');
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function test_public_and_member_shells_have_one_search_and_correct_context_switches(bool $verified): void
    {
        $member = $this->actingMember(attributes: ['email_verified_at' => $verified ? now() : null]);
        $public = $this->get('/satzung')->assertOk();
        $crawler = new Crawler($public->getContent());
        $this->assertCount(1, $crawler->filter('#navigation-search-dialog'));
        $this->assertCount(1, $crawler->filter('#main-content[tabindex="-1"]'));
        $this->assertCount(1, $crawler->filter('[data-testid="member-site-switch"]'));
        $this->assertSame(route('home', ['ansicht' => 'oeffentlich']), $crawler->filter('[data-testid="navigation-brand-link"]')->attr('href'));
        $this->assertCount(0, $crawler->filter('[data-tour-key="baxx-todos"]'));
        $this->assertCount(0, $crawler->filter('#tour-runner-root'));
        $crawler = new Crawler($this->get('/dashboard')->assertOk()->getContent());
        $this->assertCount(1, $crawler->filter('#navigation-search-dialog'));
        $this->assertCount(1, $crawler->filter('[data-testid="public-site-switch"]'));
        $this->assertSame(['Community', 'Lesen & Entdecken', 'Termine & Treffen', 'Projekte & Mitmachen', 'Verein'],
            array_column(app(NavigationBuilder::class)->build($member)['sections'], 'title'));
        $this->get('/?ansicht=oeffentlich')->assertOk()->assertSee('data-testid="nav-sections"', false);
        $this->get('/')->assertRedirect(route('dashboard'));
        $this->get('/?ansicht=anderes')->assertRedirect(route('dashboard'));
        $this->get('/?ansicht[]=oeffentlich')->assertRedirect(route('dashboard'));
    }

    #[TestWith(['reviews.show', '/rezensionen/123', 'reviews.index'])]
    #[TestWith(['reviews.edit', '/rezensionen/123/bearbeiten', 'reviews.index'])]
    #[TestWith(['fanfiction.show', '/fanfiction/123', 'fanfiction.index'])]
    #[TestWith(['todos.create', '/aufgaben/erstellen', 'todos.index'])]
    #[TestWith(['3d-modelle.edit', '/3d-modelle/123/bearbeiten', '3d-modelle.index'])]
    #[TestWith(['mitglieder.karte.locked', '/mitglieder/karte/gesperrt', 'mitglieder.karte'])]
    #[TestWith(['rpg.characters.history', '/rpg/charaktere/123/historie', 'rpg.characters.index'])]
    #[TestWith(['rewards.admin.maddraxikon', '/belohnungen/admin/maddraxikon', 'rewards.admin'])]
    #[TestWith(['newsletter.archiv.admin.edit', '/admin/newsletter-archiv/123/bearbeiten', null])]
    public function test_subpage_has_only_its_own_active_location(string $name, string $uri, ?string $expected): void
    {
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
            $this->assertCount(0, $active);
        } else {
            $this->assertCount(1, $active);
            $this->assertSame($expected, $active[0]['key']);
            $this->assertSame('location', $active[0]['aria_current']);
        }
    }
}
