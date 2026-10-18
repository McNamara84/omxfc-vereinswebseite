<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TodoStatus;
use App\Models\Team;
use App\Models\Todo;
use App\Support\Navigation\NavigationBuilder;
use App\Support\Navigation\NavigationSearch;
use App\Support\Navigation\PageNavigation;
use Database\Seeders\UxPreviewSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Livewire\Component;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\DomCrawler\Crawler;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class UxNavigationTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    public function test_public_single_destinations_are_direct_and_searchable(): void
    {
        $navigation = app(NavigationBuilder::class)->build(null, [], 'public');
        $this->assertSame(['Termine', 'Spenden'], array_column($navigation['direct'], 'title'));
        $this->assertSame(['Verein'], array_column($navigation['sections'], 'title'));
        foreach (['Termine', 'Spenden'] as $query) {
            $this->assertContains($query, array_column(app(NavigationSearch::class)->search(null, $query), 'name'));
        }
    }

    #[TestWith(['Mitglied', false])]
    #[TestWith(['Ehrenmitglied', false])]
    #[TestWith(['Admin', true])]
    #[TestWith(['Vorstand', true])]
    #[TestWith(['Kassenwart', true])]
    public function test_quick_actions_follow_existing_permissions(string $role, bool $governance): void
    {
        $member = $this->createUserWithRole(Role::from($role));
        $actions = app(NavigationBuilder::class)->quickActions($member);
        $titles = array_column($actions, 'title');
        $this->assertLessThanOrEqual(4, count($actions));
        $this->assertContains('Challenges & Baxx', $titles);
        if ($governance) {
            $this->assertContains('Challenges prüfen', $titles);
            $this->assertContains('Mitgliedsanträge', $titles);
            $this->assertStringEndsWith('#dashboard-applicants', $actions[3]['href']);
        } else {
            $this->assertNotContains('Challenges prüfen', $titles);
            $this->assertNotContains('Mitgliedsanträge', $titles);
            $this->assertContains('Termine', $titles);
        }
    }

    public function test_applicants_unverified_accounts_and_revoked_gate_get_no_shortcuts(): void
    {
        $applicant = $this->createUserWithRole(Role::Anwaerter);
        $this->assertSame([], app(NavigationBuilder::class)->quickActions($applicant));
        $member = $this->createUserWithRole(Role::Mitglied);
        $member->forceFill(['email_verified_at' => null])->save();
        $this->assertSame([], app(NavigationBuilder::class)->quickActions($member));
        $member->forceFill(['email_verified_at' => now()])->save();
        Gate::define('access-dashboard', fn () => false);
        $this->assertSame([], app(NavigationBuilder::class)->quickActions($member));
    }

    public function test_other_current_team_does_not_gain_governance_shortcuts(): void
    {
        $member = $this->createUserWithRole(Role::Vorstand);
        $other = Team::factory()->create(['name' => 'Andere AG']);
        $other->users()->attach($member, ['role' => Role::Mitglied->value]);
        $member->forceFill(['current_team_id' => $other->id])->save();
        $this->assertNotContains('Mitgliedsanträge', array_column(app(NavigationBuilder::class)->quickActions($member->fresh()), 'title'));
    }

    public function test_public_context_has_fixed_return_link_only_for_eligible_members(): void
    {
        $this->get(route('satzung'))->assertOk()->assertDontSee('data-testid="public-context"', false);
        $this->actingAs($this->createUserWithRole(Role::Mitglied))
            ->get(route('satzung').'?return=https://example.com')
            ->assertOk()->assertSee('data-testid="public-context"', false)->assertSee('Zurück zum Mitgliederbereich');
        $this->actingAs($this->createUserWithRole(Role::Anwaerter))
            ->get(route('satzung'))->assertOk()->assertDontSee('data-testid="public-context"', false);
    }

    public function test_breadcrumbs_use_authorized_parents_and_plain_group_labels(): void
    {
        $member = $this->createUserWithRole(Role::Mitglied);
        request()->setRouteResolver(fn () => (new Route('GET', '/rezensionen/1/erstellen', fn () => null))->name('reviews.create'));
        $page = app(PageNavigation::class)->resolve($member, 'member');
        $this->assertSame('Rezension schreiben', $page['title']);
        $this->assertSame(['Dashboard', 'Lesen & Entdecken', 'Rezensionen', 'Rezension schreiben'], array_column($page['breadcrumbs'], 'label'));
        $this->assertArrayNotHasKey('link', $page['breadcrumbs'][1]);
        $this->assertSame(route('reviews.index'), $page['breadcrumbs'][2]['link']);
        $this->assertArrayNotHasKey('link', $page['breadcrumbs'][3]);
    }

    public function test_dashboard_breadcrumb_is_empty_and_titles_do_not_expose_route_code(): void
    {
        request()->setRouteResolver(fn () => (new Route('GET', '/dashboard', fn () => null))->name('dashboard'));
        $this->assertSame([], app(PageNavigation::class)->resolve(null, 'public')['breadcrumbs']);
    }

    public function test_preview_admin_accounts_cannot_be_seeded_outside_testing(): void
    {
        $this->app['env'] = 'production';
        $this->expectException(\LogicException::class);
        (new UxPreviewSeeder)->run();
    }

    public function test_disabled_database_maintenance_is_hidden_from_admin_navigation_and_search(): void
    {
        $admin = $this->actingMember(Role::Admin);
        config(['database-maintenance.enabled' => false]);
        $this->get(route('dashboard'))->assertOk()->assertDontSee(route('admin.datenbank.index'), false);
        $this->getJson(route('mary.spotlight', ['search' => 'Datenbank']))->assertExactJson([]);
        config(['database-maintenance.enabled' => true]);
        $this->get(route('dashboard'))->assertOk()->assertSee(route('admin.datenbank.index'), false);
        $this->assertNotEmpty(app(NavigationSearch::class)->search($admin, 'Datenbank', 'member'));
    }

    public function test_pending_challenge_with_missing_completion_date_remains_reviewable(): void
    {
        $admin = $this->actingMember(Role::Admin);
        $assignee = $this->createUserWithRole(Role::Mitglied);
        Todo::create([
            'team_id' => $admin->current_team_id,
            'created_by' => $admin->id,
            'assigned_to' => $assignee->id,
            'title' => 'Challenge ohne Abschlussdatum',
            'points' => 5,
            'status' => TodoStatus::Completed,
            'completed_at' => null,
        ]);
        $this->get(route('todos.index', ['filter' => 'pending']))
            ->assertOk()->assertSeeText('Nicht hinterlegt')->assertSeeText('Abschluss bestätigen und Baxx vergeben');
    }

    #[TestWith(['input'])]
    #[TestWith(['password'])]
    #[TestWith(['textarea'])]
    #[TestWith(['select'])]
    #[TestWith(['file'])]
    public function test_real_mary_fields_keep_explicit_ids_and_announce_errors(string $component): void
    {
        $errors = (new ViewErrorBag)->put('default', new MessageBag(['title' => ['Bitte Titel angeben.']]));
        View::share('errors', $errors);
        if ($component === 'file') {
            $livewire = new class extends Component {};
            $livewire->setId('ux-file-field');
            View::share('__livewire', $livewire);
            View::share('_instance', $livewire);
        }
        $html = Blade::render('<x-mary-'.$component.' id="field-title" label="Titel" wire:model="title" />', ['errors' => $errors]);
        $crawler = new Crawler($html);
        $field = $crawler->filter('#field-title');
        $this->assertCount(1, $field);
        $this->assertSame('true', $field->attr('aria-invalid'));
        foreach (explode(' ', trim($field->attr('aria-describedby'))) as $id) {
            $this->assertCount(1, $crawler->filter('#'.$id));
        }
        $this->assertStringContainsString('Bitte Titel angeben.', $crawler->filter('#field-title-error')->text());
    }
}
