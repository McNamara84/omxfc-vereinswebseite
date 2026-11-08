<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\KompendiumSearchAnalyticsDashboard;
use App\Models\KompendiumSearchLog;
use App\Services\KompendiumSearchAnalyticsService;
use Livewire\Livewire;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(KompendiumSearchAnalyticsDashboardTestFixtures::class);

test('admin can access search statistics dashboard', function (): void {
    $admin = $this->actingAdmin();
    $this->actingAs($admin)->get(route('kompendium.admin.search-statistics'))->assertOk()->assertSeeLivewire(KompendiumSearchAnalyticsDashboard::class);
});

test('non admin cannot access search statistics dashboard', function (): void {
    $member = $this->actingMember(Role::Mitglied);
    $this->actingAs($member)->get(route('kompendium.admin.search-statistics'))->assertStatus(403);
});

test('guest is redirected from search statistics dashboard', function (): void {
    $this->get(route('kompendium.admin.search-statistics'))->assertRedirect(route('login'));
});

test('dashboard excludes admin searches by default and can include them', function (): void {
    $admin = $this->actingAdmin();
    $member = $this->createUserWithRole(Role::Mitglied);
    $this->createLog($member, 'Aruula');
    $this->createLog($admin, 'Adminbegriff', ['is_admin_search' => true, 'results_count' => 0]);
    Livewire::actingAs($admin)->test(KompendiumSearchAnalyticsDashboard::class)->assertSee('Aruula')->assertDontSee('Adminbegriff')->set('includeAdminSearches', true)->assertSee('Adminbegriff');
});

test('dashboard filters zero results and term', function (): void {
    $admin = $this->actingAdmin();
    $member = $this->createUserWithRole(Role::Mitglied);
    $this->createLog($member, 'Trefferbegriff', ['results_count' => 5]);
    $this->createLog($member, 'Leerlauf', ['results_count' => 0]);
    Livewire::actingAs($admin)->test(KompendiumSearchAnalyticsDashboard::class)->set('onlyZeroResults', true)->assertSee('Leerlauf')->assertDontSee('Trefferbegriff')->set('term', 'Leer')->assertSee('Leerlauf');
});

test('analytics term filter uses lowercase binding for normalized query', function (): void {
    $bindings = app(KompendiumSearchAnalyticsService::class)->query(['term' => 'Aru', 'include_admin_searches' => true])->getQuery()->getBindings();
    expect($bindings)->toContain('%Aru%')
        ->toContain('%aru%');
});

test('dashboard can reset all logs and has no export button', function (): void {
    $admin = $this->actingAdmin();
    $member = $this->createUserWithRole(Role::Mitglied);
    $this->createLog($member, 'Aruula');
    $this->createLog($member, 'Matthew');
    Livewire::actingAs($admin)->test(KompendiumSearchAnalyticsDashboard::class)->assertDontSee('CSV')->assertDontSee('Export')->call('resetLogs')->assertSee('2 Suchlog-Einträge wurden gelöscht.');
    expect(KompendiumSearchLog::query()->count())->toBe(0);
});
