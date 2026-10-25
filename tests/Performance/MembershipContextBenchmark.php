<?php

namespace Tests\Performance;

use App\Enums\Role;
use App\Livewire\MitgliederIndex;
use App\Models\Team;
use App\Services\Memberships\MembershipContext;
use App\Support\Navigation\NavigationBuilder;
use App\Support\Navigation\NavigationSearch;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

/** Run explicitly before and after the change with real request boundaries. */
class MembershipContextBenchmark extends TestCase
{
    use CreatesUserWithRole;

    public function test_measure_membership_reads(): void
    {
        $this->artisan('migrate:fresh', ['--seed' => true])->assertSuccessful();
        $users = [];
        foreach ([Role::Mitglied, Role::Vorstand, Role::Admin] as $role) {
            $users[$role->value] = $this->createUserWithRole($role);
        }
        $team = Team::membersTeam();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = ['sql' => $query->sql, 'ms' => $query->time];
        });
        $samples = [];
        foreach ($users as $role => $user) {
            $this->actingAs($user);
            foreach (range(0, 10) as $iteration) {
                $operations = [
                    'dashboard' => fn () => $this->get(route('dashboard'))->assertOk(),
                    'public' => fn () => $this->get(route('satzung'))->assertOk(),
                    'navigation' => function () use ($user): void {
                        $this->get(route('satzung'))->assertOk();
                        app(NavigationBuilder::class)->build($user, [], 'member');
                        app(NavigationSearch::class)->search($user, 'Baxx');
                    },
                    'livewire' => fn () => Livewire::test(MitgliederIndex::class)->call('sort', 'name')->assertOk(),
                    'mutation' => function () use ($team, $user, $role): void {
                        $user->mitgliederTeamRole();
                        $team->users()->updateExistingPivot($user->id, ['role' => Role::Mitglied->value]);
                        $this->assertSame(Role::Mitglied, $user->mitgliederTeamRole());
                        $team->users()->updateExistingPivot($user->id, ['role' => $role]);
                    },
                ];
                foreach ($operations as $name => $operation) {
                    if (class_exists(MembershipContext::class)) {
                        app(MembershipContext::class)->reset();
                    }
                    $queries = [];
                    memory_reset_peak_usage();
                    $start = hrtime(true);
                    $operation();
                    $elapsed = (hrtime(true) - $start) / 1e6;
                    if ($iteration > 0) {
                        $membershipQueries = array_filter($queries, fn ($query) => str_contains($query['sql'], 'team_user'));
                        $samples[$role][$name][] = [
                            'ms' => round($elapsed, 3),
                            'queries' => count($queries),
                            'membership_queries' => count($membershipQueries),
                            'sql_ms' => round(array_sum(array_column($queries, 'ms')), 3),
                            'peak_bytes' => memory_get_peak_usage(true),
                        ];
                    }
                }
            }
        }
        $output = getenv('MEMBERSHIP_BENCHMARK_OUTPUT');
        $this->assertNotFalse($output, 'MEMBERSHIP_BENCHMARK_OUTPUT must name the report file.');
        file_put_contents($output, json_encode($samples, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");
    }

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;
        RefreshDatabaseState::$inMemoryConnections = [];
        parent::tearDown();
    }
}
