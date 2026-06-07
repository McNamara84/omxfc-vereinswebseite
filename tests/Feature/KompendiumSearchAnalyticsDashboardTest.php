<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\KompendiumSearchAnalyticsDashboard;
use App\Models\KompendiumSearchLog;
use App\Models\User;
use App\Services\KompendiumSearchAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class KompendiumSearchAnalyticsDashboardTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    private function createLog(User $user, string $query, array $overrides = []): KompendiumSearchLog
    {
        return KompendiumSearchLog::query()->create(array_merge([
            'user_id' => $user->id,
            'query' => $query,
            'normalized_query' => mb_strtolower($query),
            'parsed_query' => ['terms' => [mb_strtolower($query)]],
            'selected_serien' => ['maddrax'],
            'sort' => 'relevance',
            'direction' => 'desc',
            'results_count' => 3,
            'source' => 'search_submit',
            'status' => 'ok',
            'is_admin_search' => false,
            'candidates_truncated' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    public function test_admin_can_access_search_statistics_dashboard(): void
    {
        $admin = $this->actingAdmin();

        $this->actingAs($admin)
            ->get(route('kompendium.admin.search-statistics'))
            ->assertOk()
            ->assertSeeLivewire(KompendiumSearchAnalyticsDashboard::class);
    }

    public function test_non_admin_cannot_access_search_statistics_dashboard(): void
    {
        $member = $this->actingMember(Role::Mitglied);

        $this->actingAs($member)
            ->get(route('kompendium.admin.search-statistics'))
            ->assertStatus(403);
    }

    public function test_guest_is_redirected_from_search_statistics_dashboard(): void
    {
        $this->get(route('kompendium.admin.search-statistics'))
            ->assertRedirect(route('login'));
    }

    public function test_dashboard_excludes_admin_searches_by_default_and_can_include_them(): void
    {
        $admin = $this->actingAdmin();
        $member = $this->createUserWithRole(Role::Mitglied);

        $this->createLog($member, 'Aruula');
        $this->createLog($admin, 'Adminbegriff', [
            'is_admin_search' => true,
            'results_count' => 0,
        ]);

        Livewire::actingAs($admin)
            ->test(KompendiumSearchAnalyticsDashboard::class)
            ->assertSee('Aruula')
            ->assertDontSee('Adminbegriff')
            ->set('includeAdminSearches', true)
            ->assertSee('Adminbegriff');
    }

    public function test_dashboard_filters_zero_results_and_term(): void
    {
        $admin = $this->actingAdmin();
        $member = $this->createUserWithRole(Role::Mitglied);

        $this->createLog($member, 'Trefferbegriff', ['results_count' => 5]);
        $this->createLog($member, 'Leerlauf', ['results_count' => 0]);

        Livewire::actingAs($admin)
            ->test(KompendiumSearchAnalyticsDashboard::class)
            ->set('onlyZeroResults', true)
            ->assertSee('Leerlauf')
            ->assertDontSee('Trefferbegriff')
            ->set('term', 'Leer')
            ->assertSee('Leerlauf');
    }

    public function test_analytics_term_filter_uses_lowercase_binding_for_normalized_query(): void
    {
        $bindings = app(KompendiumSearchAnalyticsService::class)
            ->query(['term' => 'Aru', 'include_admin_searches' => true])
            ->getQuery()
            ->getBindings();

        $this->assertContains('%Aru%', $bindings);
        $this->assertContains('%aru%', $bindings);
    }

    public function test_dashboard_can_reset_all_logs_and_has_no_export_button(): void
    {
        $admin = $this->actingAdmin();
        $member = $this->createUserWithRole(Role::Mitglied);
        $this->createLog($member, 'Aruula');
        $this->createLog($member, 'Matthew');

        Livewire::actingAs($admin)
            ->test(KompendiumSearchAnalyticsDashboard::class)
            ->assertDontSee('CSV')
            ->assertDontSee('Export')
            ->call('resetLogs')
            ->assertSee('2 Suchlog-Einträge wurden gelöscht.');

        $this->assertSame(0, KompendiumSearchLog::query()->count());
    }
}
