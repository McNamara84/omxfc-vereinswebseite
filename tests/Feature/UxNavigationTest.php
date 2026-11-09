<?php

declare(strict_types=1);

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
use Symfony\Component\DomCrawler\Crawler;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CreatesUserWithRole::class, RefreshDatabase::class);

test('public single destinations are direct and searchable', function (): void {
    $navigation = app(NavigationBuilder::class)->build(null, [], 'public');
    expect(array_column($navigation['direct'], 'title'))->toBe(['Termine', 'Spenden'])
        ->and(array_column($navigation['sections'], 'title'))->toBe(['Verein']);
    foreach (['Termine', 'Spenden'] as $query) {
        expect(array_column(app(NavigationSearch::class)->search(null, $query), 'name'))->toContain($query);
    }
});

test('quick actions follow existing permissions', function (string $role, bool $governance): void {
    $member = $this->createUserWithRole(Role::from($role));
    $actions = app(NavigationBuilder::class)->quickActions($member);
    $titles = array_column($actions, 'title');
    expect(count($actions))->toBeLessThanOrEqual(4)
        ->and($titles)->toContain('Challenges & Baxx');
    if ($governance) {
        expect($titles)->toContain('Challenges prüfen')
            ->toContain('Mitgliedsanträge')
            ->and($actions[3]['href'])->toEndWith('#dashboard-applicants');
    } else {
        expect($titles)->not->toContain('Challenges prüfen')->not->toContain('Mitgliedsanträge')
            ->toContain('Termine');
    }
})->with([['Mitglied', false], ['Ehrenmitglied', false], ['Admin', true], ['Vorstand', true], ['Kassenwart', true]]);

test('missing email confirmation does not change role based shortcuts', function (Role $role): void {
    $member = $this->createUserWithRole($role);
    $actions = app(NavigationBuilder::class)->quickActions($member);
    expect($actions)->not->toBeEmpty();
    $member->forceFill(['email_verified_at' => null])->save();
    expect(app(NavigationBuilder::class)->quickActions($member))->toBe($actions);
})->with([[Role::Mitglied], [Role::Admin]]);

test('applicants and revoked gate get no shortcuts', function (): void {
    $applicant = $this->createUserWithRole(Role::Anwaerter);
    expect(app(NavigationBuilder::class)->quickActions($applicant))->toBe([]);
    $member = $this->createUserWithRole(Role::Mitglied);
    $member->forceFill(['email_verified_at' => null])->save();
    Gate::define('access-dashboard', fn () => false);
    expect(app(NavigationBuilder::class)->quickActions($member))->toBe([]);
});

test('other current team does not gain governance shortcuts', function (): void {
    $member = $this->createUserWithRole(Role::Vorstand);
    $other = Team::factory()->create(['name' => 'Andere AG']);
    $other->users()->attach($member, ['role' => Role::Mitglied->value]);
    $member->forceFill(['current_team_id' => $other->id])->save();
    expect(array_column(app(NavigationBuilder::class)->quickActions($member->fresh()), 'title'))->not->toContain('Mitgliedsanträge');
});

test('public context has fixed return link only for eligible members', function (bool $verified): void {
    $this->get(route('satzung'))->assertOk()->assertDontSee('data-testid="public-context"', false);
    $this->actingMember(attributes: ['email_verified_at' => $verified ? now() : null]);
    $this->get(route('satzung').'?return=https://example.com')->assertOk()->assertSee('data-testid="public-context"', false)->assertSee('Zurück zum Mitgliederbereich');
    $this->actingAs($this->createUserWithRole(Role::Anwaerter))->get(route('satzung'))->assertOk()->assertDontSee('data-testid="public-context"', false);
})->with([[true], [false]]);

test('breadcrumbs use authorized parents and plain group labels', function (bool $verified): void {
    $member = $this->createUserWithRole(Role::Mitglied);
    $member->forceFill(['email_verified_at' => $verified ? now() : null])->save();
    request()->setRouteResolver(fn () => (new Route('GET', '/rezensionen/1/erstellen', fn () => null))->name('reviews.create'));
    $page = app(PageNavigation::class)->resolve($member, 'member');
    expect($page['title'])->toBe('Rezension schreiben')
        ->and(array_column($page['breadcrumbs'], 'label'))->toBe(['Dashboard', 'Lesen & Entdecken', 'Rezensionen', 'Rezension schreiben'])
        ->and($page['breadcrumbs'][1])->not->toHaveKey('link')
        ->and($page['breadcrumbs'][2]['link'])->toBe(route('reviews.index'))
        ->and($page['breadcrumbs'][3])->not->toHaveKey('link');
})->with([[true], [false]]);

test('dashboard breadcrumb is empty and titles do not expose route code', function (): void {
    request()->setRouteResolver(fn () => (new Route('GET', '/dashboard', fn () => null))->name('dashboard'));
    expect(app(PageNavigation::class)->resolve(null, 'public')['breadcrumbs'])->toBe([]);
});

test('preview admin accounts cannot be seeded outside testing', function (): void {
    $this->app['env'] = 'production';
    expect(fn () => (new UxPreviewSeeder)->run())->toThrow(\LogicException::class);
});

test('disabled database maintenance is hidden from admin navigation and search', function (): void {
    $admin = $this->actingMember(Role::Admin);
    config(['database-maintenance.enabled' => false]);
    $this->get(route('dashboard'))->assertOk()->assertDontSee(route('admin.datenbank.index'), false);
    $this->getJson(route('mary.spotlight', ['search' => 'Datenbank']))->assertExactJson([]);
    config(['database-maintenance.enabled' => true]);
    $this->get(route('dashboard'))->assertOk()->assertSee(route('admin.datenbank.index'), false);
    expect(app(NavigationSearch::class)->search($admin, 'Datenbank', 'member'))->not->toBeEmpty();
});

test('pending challenge with missing completion date remains reviewable', function (): void {
    $admin = $this->actingMember(Role::Admin);
    $assignee = $this->createUserWithRole(Role::Mitglied);
    Todo::create(['team_id' => $admin->current_team_id, 'created_by' => $admin->id, 'assigned_to' => $assignee->id, 'title' => 'Challenge ohne Abschlussdatum', 'points' => 5, 'status' => TodoStatus::Completed, 'completed_at' => null]);
    $this->get(route('todos.index', ['filter' => 'pending']))->assertOk()->assertSeeText('Nicht hinterlegt')->assertSeeText('Abschluss bestätigen und Baxx vergeben');
});

test('real mary fields keep explicit ids and announce errors', function (string $component): void {
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
    expect($field)->toHaveCount(1)
        ->and($field->attr('aria-invalid'))->toBe('true');
    foreach (explode(' ', trim($field->attr('aria-describedby'))) as $id) {
        expect($crawler->filter('#'.$id))->toHaveCount(1);
    }
    expect($crawler->filter('#field-title-error')->text())->toContain('Bitte Titel angeben.');
})->with([['input'], ['password'], ['textarea'], ['select'], ['file']]);
