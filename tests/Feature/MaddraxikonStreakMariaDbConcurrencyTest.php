<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonContribution;
use App\Models\MaddraxikonStreakBooking;
use App\Models\MaddraxikonSyncState;
use App\Models\Team;
use App\Models\User;
use App\Services\Maddraxikon\AccountLinkService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MaddraxikonStreakMariaDbConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private bool $committed = false;

    public function test_competing_evaluators_and_rechecks_book_each_difference_once(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Needs MariaDB with separate worker processes.');
        }
        $this->assertSame('omxfc_maddraxikon_test', DB::getDatabaseName());
        $this->assertStringContainsString('MariaDB', DB::selectOne('SELECT VERSION() AS version')->version);
        $this->travelTo(CarbonImmutable::parse('2026-10-20T12:00:00+02:00'));
        config(['maddraxikon.streaks.enabled' => true, 'maddraxikon.streaks.starts_at' => '2026-10-01T00:00:00+02:00']);
        $member = User::factory()->create();
        Team::membersTeam()->users()->attach($member, ['role' => Role::Mitglied->value]);
        $link = MaddraxikonAccountLink::factory()->create(['user_id' => $member->id, 'verified_at' => '2026-09-30T00:00:00Z']);
        MaddraxikonSyncState::factory()->create(['initial_watermark_at' => '2026-09-29T00:00:00Z', 'watermark_at' => now(), 'last_succeeded_at' => now()]);
        $sources = [];
        foreach ([10, 11, 12, 13, 14] as $day) {
            $source = MaddraxikonContribution::factory()->create(['user_id' => $member->id, 'account_link_id' => $link->id, 'wiki_user_id' => $link->wiki_user_id, 'occurred_at' => "2026-10-{$day}T12:00:00+02:00"]);
            $sources[] = $source;
        }
        DB::connection()->commit();
        $this->committed = true;
        $this->workers(0);
        $this->assertSame(15, (int) MaddraxikonStreakBooking::query()->sum('points_delta'));
        $this->assertDatabaseCount('maddraxikon_streak_bookings', 5);
        MaddraxikonContribution::query()->update(['streak_next_check_epoch' => 0]);
        $this->workers($sources[2]->revision_id);
        $this->assertSame(6, (int) MaddraxikonStreakBooking::query()->sum('points_delta'));
        $this->assertDatabaseCount('maddraxikon_streak_bookings', 8);
    }

    public function test_disconnecting_while_workers_wait_for_api_prevents_new_payouts(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Needs MariaDB with separate worker processes.');
        }
        $this->assertSame('omxfc_maddraxikon_test', DB::getDatabaseName());
        $this->travelTo(CarbonImmutable::parse('2026-10-20T12:00:00+02:00'));
        $member = User::factory()->create();
        Team::membersTeam()->users()->attach($member, ['role' => Role::Mitglied->value]);
        $link = MaddraxikonAccountLink::factory()->create(['user_id' => $member->id, 'verified_at' => '2026-09-30T00:00:00Z']);
        MaddraxikonSyncState::factory()->create(['initial_watermark_at' => '2026-09-29T00:00:00Z', 'watermark_at' => now(), 'last_succeeded_at' => now()]);
        MaddraxikonContribution::factory()->create(['user_id' => $member->id, 'account_link_id' => $link->id, 'wiki_user_id' => $link->wiki_user_id, 'occurred_at' => '2026-10-10T12:00:00+02:00']);
        DB::connection()->commit();
        $this->committed = true;
        $this->workers(0, fn () => app(AccountLinkService::class)->disconnect($member));
        $this->assertDatabaseCount('maddraxikon_streak_bookings', 0);
        $this->assertDatabaseCount('user_points', 0);
        $this->assertFalse($link->fresh()->isActive());
    }

    private function workers(int $invalidRevision, ?callable $afterReady = null): void
    {
        $workers = [];
        try {
            for ($i = 0; $i < 2; $i++) {
                $pipes = [];
                $process = proc_open([PHP_BINARY, base_path('tests/Support/MariaDbMaddraxikonStreakWorker.php'), (string) $invalidRevision], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
                $this->assertIsResource($process);
                stream_set_timeout($pipes[1], 30);
                $workers[] = [$process, $pipes];
            }
            foreach ($workers as [, $pipes]) {
                $this->assertSame("ready\n", fgets($pipes[1]));
            }
            if ($afterReady) {
                $afterReady();
            }
            foreach ($workers as [, $pipes]) {
                fwrite($pipes[0], "go\n");
                fflush($pipes[0]);
            }
            foreach ($workers as [, $pipes]) {
                $result = fgets($pipes[1]);
                $this->assertIsString($result);
                $this->assertArrayHasKey('bookings', json_decode($result, true, flags: JSON_THROW_ON_ERROR));
            }
        } finally {
            foreach ($workers as [$process, $pipes]) {
                foreach ($pipes as $pipe) {
                    fclose($pipe);
                }
                $status = proc_get_status($process);
                if ($status['running']) {
                    proc_terminate($process);
                }
                proc_close($process);
            }
        }
    }

    protected function tearDown(): void
    {
        try {
            if ($this->committed && isset($this->app)) {
                $this->artisan('migrate:fresh')->assertExitCode(0);
            }
        } finally {
            parent::tearDown();
        }
    }
}
