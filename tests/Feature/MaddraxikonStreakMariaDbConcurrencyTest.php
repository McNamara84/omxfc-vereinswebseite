<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonContribution;
use App\Models\MaddraxikonStreakBooking;
use App\Models\MaddraxikonStreakRun;
use App\Models\MaddraxikonSyncState;
use App\Models\Team;
use App\Models\User;
use App\Services\Maddraxikon\AccountLinkService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(MaddraxikonStreakMariaDbConcurrencyTestFixtures::class);
dataset('MaddraxikonStreakMariaDbConcurrencyTest.membershipChangesDuringRecheck', static fn (): array => [['none'], ['disconnected'], ['demoted'], ['removed']]);
afterEach(function (): void {
    try {
        if ($this->committed && isset($this->app)) {
            $this->artisan('migrate:fresh')->assertExitCode(0);
        }
    } finally {
    }
});
test('competing evaluators and rechecks book each difference once', function (string $change): void {
    if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('Needs MariaDB with separate worker processes.');
    }
    expect(DB::getDatabaseName())->toBe('omxfc_maddraxikon_test')
        ->and(DB::selectOne('SELECT VERSION() AS version')->version)->toContain('MariaDB');
    Mail::fake();
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
    expect((int) MaddraxikonStreakBooking::query()->sum('points_delta'))->toBe(15);
    $this->assertDatabaseCount('maddraxikon_streak_bookings', 5);
    MaddraxikonContribution::query()->update(['streak_next_check_epoch' => 0]);
    $this->workers($sources[2]->revision_id, fn () => match ($change) {
        'none' => null,
        'disconnected' => app(AccountLinkService::class)->disconnect($member),
        'demoted' => Team::membersTeam()->users()->updateExistingPivot($member->id, ['role' => Role::Anwaerter->value]),
        'removed' => Team::membersTeam()->users()->detach($member),
    });
    expect((int) MaddraxikonStreakBooking::query()->sum('points_delta'))->toBe(6);
    $this->assertDatabaseCount('maddraxikon_streak_bookings', 8);
    expect($sources[2]->fresh()->streak_status)->toBe('invalid')
        ->and($sources[2]->fresh()->streak_check_version)->toBe(2);
})->with('MaddraxikonStreakMariaDbConcurrencyTest.membershipChangesDuringRecheck');
test('disconnecting while workers wait for api prevents new payouts', function (): void {
    if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('Needs MariaDB with separate worker processes.');
    }
    expect(DB::getDatabaseName())->toBe('omxfc_maddraxikon_test');
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
    expect($link->fresh()->isActive())->toBeFalse();
});
test('competing first activations persist one rollout and matching import boundary', function (): void {
    if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('Needs MariaDB with separate worker processes.');
    }
    expect(DB::getDatabaseName())->toBe('omxfc_maddraxikon_test');
    $this->assertDatabaseCount('maddraxikon_streak_runs', 0);
    $this->assertDatabaseCount('maddraxikon_sync_states', 0);
    DB::connection()->commit();
    $this->committed = true;
    $this->workers(0, activate: true);
    $start = CarbonImmutable::parse('2026-10-20T12:00:00+02:00')->timestamp;
    expect(MaddraxikonStreakRun::query()->sole()->starts_at_epoch)->toBe($start);
    $sync = MaddraxikonSyncState::query()->sole();
    expect($sync->initial_watermark_at_epoch)->toBe($start)
        ->and($sync->watermark_at_epoch)->toBe($start)
        ->and($sync->last_succeeded_at)->toBeNull();
    $this->assertDatabaseCount('maddraxikon_streak_bookings', 0);
});
