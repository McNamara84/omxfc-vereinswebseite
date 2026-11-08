<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\MaddraxikonStreakPanel;
use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonContribution;
use App\Models\MaddraxikonStreakRun;
use App\Models\MaddraxikonSyncState;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPoint;
use App\Services\Maddraxikon\MaddraxikonApiClient;
use App\Services\Maddraxikon\MaddraxikonContributionImporter;
use App\Services\Maddraxikon\MaddraxikonMonitoring;
use App\Services\Maddraxikon\MaddraxikonStreakService;
use App\Services\Maddraxikon\MaddraxikonStreakSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(MaddraxikonStreakActivationTestFixtures::class);
dataset('MaddraxikonStreakActivationTest.changedConfigurationImports', static fn (): array => ['later start during normal import' => ['2026-11-01T00:00:00+01:00', false], 'earlier start during normal import' => ['2026-10-23T12:00:00Z', false], 'later start during recovery' => ['2026-11-01T00:00:00+01:00', true], 'earlier start during recovery' => ['2026-10-23T12:00:00Z', true]]);
dataset('MaddraxikonStreakActivationTest.runtimeActivations', static fn (): array => ['import with automatic start' => ['import', false], 'import with explicit start' => ['import', true], 'evaluation with automatic start' => ['evaluate', false], 'evaluation with explicit start' => ['evaluate', true], 'recheck with automatic start' => ['recheck', false], 'recheck with explicit start' => ['recheck', true]]);
dataset('MaddraxikonStreakActivationTest.existingImportBoundaries', static fn (): array => [[true, false], [true, true], [false, false], [false, true]]);
dataset('MaddraxikonStreakActivationTest.unfinishedImportStates', static fn (): array => [[false], [true]]);
beforeEach(function (): void {
    $this->withoutVite();
    $this->travelTo(CarbonImmutable::parse('2026-10-24T12:00:00Z'));
    config(['maddraxikon.streaks.enabled' => true, 'maddraxikon.streaks.starts_at' => null]);
});
test('config defaults to enabled and still honors explicit false', function (): void {
    $key = 'MADDRAXIKON_STREAKS_ENABLED';
    $environment = $_ENV[$key] ?? null;
    $server = $_SERVER[$key] ?? null;
    $process = getenv($key);
    unset($_ENV[$key], $_SERVER[$key]);
    putenv($key);
    try {
        $config = require config_path('maddraxikon.php');
        expect($config['streaks']['enabled'])->toBeTrue();
        $_ENV[$key] = 'false';
        $config = require config_path('maddraxikon.php');
        expect($config['streaks']['enabled'])->toBeFalse();
    } finally {
        unset($_ENV[$key], $_SERVER[$key]);
        if ($environment !== null) {
            $_ENV[$key] = $environment;
        }
        if ($server !== null) {
            $_SERVER[$key] = $server;
        }
        $process === false ? putenv($key) : putenv($key.'='.$process);
    }
});
test('deployment activates panel and persists an immutable start without env date', function (): void {
    $epoch = now()->timestamp;
    $migration = $this->migration();
    $migration->up();
    $settings = app(MaddraxikonStreakSettings::class);
    expect($settings->enabled())->toBeTrue()
        ->and($settings->start()->timestamp)->toBe($epoch)
        ->and(MaddraxikonSyncState::query()->sole()->initial_watermark_at_epoch)->toBe($epoch)
        ->and(MaddraxikonSyncState::query()->sole()->last_succeeded_at)->toBeNull();
    $member = User::factory()->create();
    Team::membersTeam()->users()->attach($member, ['role' => Role::Mitglied->value]);
    $this->actingAs($member);
    Livewire::test(MaddraxikonStreakPanel::class)->assertSee('Maddraxikon-Serie');
    $this->travelTo(now()->addDays(2));
    $migration->down();
    $migration->up();
    expect(app(MaddraxikonStreakSettings::class)->start()->timestamp)->toBe($epoch);
    $this->assertDatabaseCount('maddraxikon_streak_runs', 1);
    expect(MaddraxikonSyncState::query()->sole()->watermark_at_epoch)->toBe($epoch);
});
test('existing series and recovery progress are preserved', function (): void {
    $start = CarbonImmutable::parse('2026-10-01T00:00:00+02:00')->timestamp;
    MaddraxikonStreakRun::query()->create(['wiki_key' => config('maddraxikon.wiki_key'), 'starts_at_epoch' => $start]);
    $sync = MaddraxikonSyncState::factory()->create(['initial_watermark_at' => '2026-09-01T00:00:00Z', 'watermark_at' => '2026-10-20T00:00:00Z', 'recovery_required_at' => now(), 'recovery_from_at' => '2026-10-20T00:00:00Z', 'recovery_until_at' => now()]);
    $before = $sync->fresh()->getRawOriginal();
    $this->migration()->up();
    expect(app(MaddraxikonStreakSettings::class)->start()->timestamp)->toBe($start)
        ->and($sync->fresh()->getRawOriginal())->toBe($before);
});
test('explicitly disabled rollout creates no activation or import state', function (): void {
    config(['maddraxikon.streaks.enabled' => false]);
    $this->migration()->up();
    expect(app(MaddraxikonStreakSettings::class)->activate())->toBeNull()
        ->and(app(MaddraxikonStreakService::class)->run())->toBe(0)
        ->and(app(MaddraxikonStreakSettings::class)->enabled())->toBeFalse()
        ->and(app(MaddraxikonStreakSettings::class)->start())->toBeNull();
    $this->assertDatabaseCount('maddraxikon_streak_runs', 0);
    $this->assertDatabaseCount('maddraxikon_sync_states', 0);
});
test('explicit start survives removing the optional env override', function (): void {
    config(['maddraxikon.streaks.starts_at' => '2026-11-01T00:00:00+01:00']);
    $this->migration()->up();
    expect(MaddraxikonSyncState::query()->sole()->initial_watermark_at_epoch)->toBe(now()->timestamp)
        ->and(MaddraxikonSyncState::query()->sole()->watermark_at_epoch)->toBe(now()->timestamp);
    config(['maddraxikon.streaks.starts_at' => '']);
    expect(app(MaddraxikonStreakSettings::class)->start()->toIso8601String())->toBe('2026-10-31T23:00:00+00:00');
    config(['maddraxikon.streaks.enabled' => false]);
    $this->travelTo(now()->addDays(20));
    config(['maddraxikon.streaks.enabled' => true]);
    expect(app(MaddraxikonStreakSettings::class)->start()->toIso8601String())->toBe('2026-10-31T23:00:00+00:00');
});
test('first scheduled import covers launch but never awards prelaunch activity', function (): void {
    config(['maddraxikon.features.sync_enabled' => true, 'maddraxikon.features.awards_enabled' => true]);
    $this->migration()->up();
    $launch = CarbonImmutable::now();
    $member = User::factory()->create();
    Team::membersTeam()->users()->attach($member, ['role' => Role::Mitglied->value]);
    $link = MaddraxikonAccountLink::factory()->create(['user_id' => $member->id, 'verified_at' => now()->subDays(2)]);
    $changes = [];
    foreach ([-1, 0] as $index => $offset) {
        $changes[] = ['type' => 'edit', 'ns' => 0, 'title' => 'Starttest', 'pageid' => 42, 'revid' => 100 + $index, 'old_revid' => 99, 'rcid' => 100 + $index, 'user' => $link->wiki_username, 'userid' => $link->wiki_user_id, 'oldlen' => 100, 'newlen' => 99, 'tags' => [], 'timestamp' => $launch->addSeconds($offset)->utc()->format('Y-m-d\TH:i:s\Z')];
    }
    $api = Mockery::mock(MaddraxikonApiClient::class);
    $api->shouldReceive('recentChanges')->once()->withArgs(fn ($from, $until) => $from->lte($launch) && $until->gt($launch))->andReturn($changes);
    $api->shouldReceive('streakRevisionDetails')->once()->with([101])->andReturn([101 => ['exists' => true, 'page_id' => 42, 'user_id' => $link->wiki_user_id, 'tags' => []]]);
    $api->shouldReceive('streakPageDetails')->once()->with([42])->andReturn([42 => ['exists' => true]]);
    $this->app->instance(MaddraxikonApiClient::class, $api);
    $this->travelTo($launch->addMinutes(15));
    expect(app(MaddraxikonContributionImporter::class)->sync())->toBe(2)
        ->and(MaddraxikonContribution::query()->where('revision_id', 100)->sole()->streak_activity_date)->toBeNull()
        ->and(app(MaddraxikonStreakService::class)->run())->toBe(0);
    $this->travelTo($launch->addDay());
    expect(app(MaddraxikonStreakService::class)->run())->toBe(1)
        ->and((int) UserPoint::query()->where('user_id', $member->id)->sum('points'))->toBe(1);
});
test('changed configuration preserves rollout imports and display', function (string $configuredStart, bool $recovery): void {
    config(['maddraxikon.features.sync_enabled' => true, 'maddraxikon.features.awards_enabled' => true]);
    $this->migration()->up();
    $launch = CarbonImmutable::now();
    $member = User::factory()->create();
    Team::membersTeam()->users()->attach($member, ['role' => Role::Mitglied->value]);
    $link = MaddraxikonAccountLink::factory()->create([
        'user_id' => $member->id,
        // Re-consent must not hide contributions from the original participation period.
        'verified_at' => $launch->addMinutes(10),
        'streak_verified_at_epoch' => $launch->subDays(2)->timestamp,
    ]);
    config(['maddraxikon.streaks.starts_at' => $configuredStart]);
    $this->travelTo($launch->addMinutes(15));
    $sync = MaddraxikonSyncState::query()->sole();
    if ($recovery) {
        $sync->update(['recovery_required_at' => now(), 'recovery_from_at' => $launch, 'recovery_until_at' => now()]);
    }
    $changes = [];
    foreach ([-1, 0, 60] as $index => $offset) {
        $changes[] = ['type' => 'edit', 'ns' => 0, 'title' => 'Fester Serienstart', 'pageid' => 42, 'revid' => 100 + $index, 'old_revid' => 99, 'parentid' => 99, 'rcid' => 100 + $index, 'user' => $link->wiki_username, 'userid' => $link->wiki_user_id, 'oldlen' => 100, 'newlen' => 99, 'size' => 99, 'sizediff' => -1, 'tags' => [], 'timestamp' => $launch->addSeconds($offset)->utc()->format('Y-m-d\TH:i:s\Z')];
    }
    $api = Mockery::mock(MaddraxikonApiClient::class);
    $api->shouldReceive($recovery ? 'userContributions' : 'recentChanges')->once()->andReturn($changes);
    $api->shouldReceive('streakRevisionDetails')->once()->with([101, 102])->andReturn([101 => ['exists' => true, 'page_id' => 42, 'user_id' => $link->wiki_user_id, 'tags' => []], 102 => ['exists' => true, 'page_id' => 42, 'user_id' => $link->wiki_user_id, 'tags' => []]]);
    $api->shouldReceive('streakPageDetails')->once()->with([42])->andReturn([42 => ['exists' => true]]);
    $this->app->instance(MaddraxikonApiClient::class, $api);
    $importer = app(MaddraxikonContributionImporter::class);
    expect($recovery ? $importer->recover($launch, CarbonImmutable::now()) : $importer->sync())->toBe(2)
        ->and(MaddraxikonContribution::query()->orderBy('revision_id')->pluck('revision_id')->all())->toBe([101, 102])
        ->and(MaddraxikonContribution::query()->distinct()->pluck('streak_activity_date')->all())->toBe(['2026-10-24'])
        ->and(app(MaddraxikonStreakSettings::class)->start()->timestamp)->toBe($launch->timestamp);
    $this->actingAs($member);
    Livewire::test(MaddraxikonStreakPanel::class)->assertSee('Maddraxikon-Serie')->assertSee('Aktivität erkannt – Bonus wird geprüft.')->assertViewHas('streak', fn ($streak) => $streak['today_pending'] && ! $streak['delayed']);
    app(MaddraxikonMonitoring::class)->recordSchedulerHeartbeat();
    expect(Artisan::call('maddraxikon:status', ['--skip-api' => true]))->toBe(0)
        ->and(Artisan::output())->toMatch('/\| Start\s+\| '.preg_quote($launch->utc()->toIso8601String(), '/').'\s+\|/');
    $this->travelTo($launch->addDay()->addMinute());
    $sync->refresh()->update(['watermark_at' => now(), 'last_succeeded_at' => now()]);
    // Recovery retains its independent requirement for a verified historical bot status.
    expect(app(MaddraxikonStreakService::class)->run())->toBe($recovery ? 0 : 1);
    expect((int) UserPoint::query()->where('user_id', $member->id)->sum('points'))->toBe($recovery ? 0 : 1);
    $this->migration()->up();
    expect(MaddraxikonStreakRun::query()->sole()->starts_at_epoch)->toBe($launch->timestamp);
})->with('MaddraxikonStreakActivationTest.changedConfigurationImports');
test('disabled deployment can activate later without rerunning migrations', function (string $entry, bool $explicit): void {
    config(['maddraxikon.streaks.enabled' => false]);
    $this->migration()->up();
    $this->assertDatabaseCount('maddraxikon_streak_runs', 0);
    $this->assertDatabaseCount('maddraxikon_sync_states', 0);
    $this->travel(2)->days();
    $activation = CarbonImmutable::now('UTC')->startOfSecond();
    $start = $explicit ? $activation->subMinutes(15) : $activation;
    config(['maddraxikon.streaks.enabled' => true, 'maddraxikon.streaks.starts_at' => $explicit ? $start->toIso8601String() : null, 'maddraxikon.features.sync_enabled' => true, 'maddraxikon.features.awards_enabled' => true]);
    $member = User::factory()->create();
    Team::membersTeam()->users()->attach($member, ['role' => Role::Mitglied->value]);
    $link = MaddraxikonAccountLink::factory()->create(['user_id' => $member->id, 'verified_at' => $start->subDay()]);
    $changes = [];
    foreach ([-1, 0] as $index => $offset) {
        $changes[] = ['type' => 'edit', 'ns' => 0, 'title' => 'Späte Aktivierung', 'pageid' => 42, 'revid' => 100 + $index, 'old_revid' => 99, 'rcid' => 100 + $index, 'user' => $link->wiki_username, 'userid' => $link->wiki_user_id, 'oldlen' => 100, 'newlen' => 99, 'tags' => [], 'timestamp' => $start->addSeconds($offset)->format('Y-m-d\TH:i:s\Z')];
    }
    $api = Mockery::mock(MaddraxikonApiClient::class);
    $api->shouldReceive('recentChanges')->once()->withArgs(fn ($from, $until) => $from->lte($start) && $until->gt($start))->andReturn($changes);
    $api->shouldReceive('streakRevisionDetails')->once()->with([101])->andReturn([101 => ['exists' => true, 'page_id' => 42, 'user_id' => $link->wiki_user_id, 'tags' => []]]);
    $api->shouldReceive('streakPageDetails')->once()->with([42])->andReturn([42 => ['exists' => true]]);
    $this->app->instance(MaddraxikonApiClient::class, $api);
    $service = app(MaddraxikonStreakService::class);
    $importer = app(MaddraxikonContributionImporter::class);
    $alreadyImported = $entry === 'import' && $explicit;
    expect($entry === 'import' ? $importer->sync() : $service->run($entry === 'recheck'))->toBe($alreadyImported ? 2 : 0);
    $this->assertDatabaseCount('maddraxikon_streak_runs', 1);
    expect(MaddraxikonStreakRun::query()->sole()->starts_at_epoch)->toBe($start->timestamp);
    $sync = MaddraxikonSyncState::query()->sole();
    expect($sync->initial_watermark_at_epoch)->toBe($start->timestamp)
        ->and($service->covered($sync))->toBeTrue();
    if (! $alreadyImported) {
        expect($sync->last_succeeded_at)->toBeNull()
            ->and($sync->watermark_at_epoch)->toBe($start->timestamp);
        $this->travelTo($activation->addMinutes(15));
        expect($importer->sync())->toBe(2);
    }
    expect(MaddraxikonContribution::query()->where('revision_id', 100)->sole()->streak_activity_date)->toBeNull()
        ->and($service->run())->toBe(0);
    $this->travelTo($start->addHours(24));
    expect($service->run())->toBe(1)
        ->and($service->run())->toBe(0)
        ->and((int) UserPoint::query()->where('user_id', $member->id)->sum('points'))->toBe(1)
        ->and(app(MaddraxikonStreakSettings::class)->start()->timestamp)->toBe($start->timestamp)
        ->and($sync->fresh()->initial_watermark_at_epoch)->toBe($start->timestamp);
})->with('MaddraxikonStreakActivationTest.runtimeActivations');
test('runtime activation preserves existing import and recovery boundaries', function (bool $covered, bool $recovery): void {
    $start = CarbonImmutable::now('UTC')->subHour();
    config(['maddraxikon.streaks.starts_at' => $start->toIso8601String()]);
    $sync = MaddraxikonSyncState::factory()->create(['initial_watermark_at' => $covered ? $start->subDay() : $start->addMinutes(30), 'watermark_at' => now()->subMinutes(10), 'recovery_required_at' => $recovery ? now() : null, 'recovery_from_at' => $recovery ? now()->subMinutes(10) : null, 'recovery_until_at' => $recovery ? now() : null]);
    $before = $sync->fresh()->getAttributes();
    $settings = app(MaddraxikonStreakSettings::class);
    $run = $settings->activate();
    expect($run->starts_at_epoch)->toBe($start->timestamp)
        ->and($sync->fresh()->getAttributes())->toBe($before)
        ->and(app(MaddraxikonStreakService::class)->covered($sync->fresh()))->toBe($covered && ! $recovery);
    config(['maddraxikon.streaks.starts_at' => '2030-01-01T00:00:00Z']);
    $this->travel(1)->days();
    expect($settings->activate()->id)->toBe($run->id)
        ->and($settings->start()->timestamp)->toBe($start->timestamp)
        ->and($sync->fresh()->getAttributes())->toBe($before);
})->with('MaddraxikonStreakActivationTest.existingImportBoundaries');
test('runtime activation recovers missing sync state without moving a pinned start', function (): void {
    $start = CarbonImmutable::now('UTC')->subHours(2);
    $run = MaddraxikonStreakRun::query()->create(['wiki_key' => config('maddraxikon.wiki_key'), 'starts_at_epoch' => $start->timestamp]);
    config(['maddraxikon.streaks.starts_at' => '2030-01-01T00:00:00Z']);
    expect(app(MaddraxikonStreakSettings::class)->activate()->id)->toBe($run->id);
    $sync = MaddraxikonSyncState::query()->sole();
    expect($sync->initial_watermark_at_epoch)->toBe($start->timestamp)
        ->and($sync->watermark_at_epoch)->toBe($start->timestamp)
        ->and($sync->last_succeeded_at)->toBeNull();
});
test('explicit start outside recent changes retention requires recovery instead of skipping history', function (): void {
    $start = CarbonImmutable::now('UTC')->subDays(40);
    config(['maddraxikon.features.sync_enabled' => true, 'maddraxikon.streaks.starts_at' => $start->toIso8601String()]);
    $api = Mockery::mock(MaddraxikonApiClient::class);
    $api->shouldNotReceive('recentChanges');
    $this->app->instance(MaddraxikonApiClient::class, $api);
    expect(app(MaddraxikonContributionImporter::class)->sync())->toBe(0);
    $sync = MaddraxikonSyncState::query()->sole();
    expect($sync->initial_watermark_at_epoch)->toBe($start->timestamp)
        ->and($sync->watermark_at_epoch)->toBe($start->timestamp)
        ->and($sync->recovery_required_at)->not->toBeNull()
        ->and(app(MaddraxikonStreakService::class)->covered($sync))->toBeFalse();
});
test('preview and status do not initialize a later activation', function (): void {
    $this->artisan('maddraxikon:streaks', ['--pretend' => true])->assertSuccessful();
    $this->artisan('maddraxikon:status', ['--skip-api' => true])->assertSuccessful();
    $this->assertDatabaseCount('maddraxikon_streak_runs', 0);
    $this->assertDatabaseCount('maddraxikon_sync_states', 0);
});
test('activation preserves an existing initial boundary or open recovery without a watermark', function (bool $recovery): void {
    $initial = CarbonImmutable::now('UTC')->subDay();
    $sync = MaddraxikonSyncState::factory()->create(['initial_watermark_at' => $initial, 'watermark_at' => null, 'last_succeeded_at' => null, 'recovery_required_at' => $recovery ? now() : null, 'recovery_from_at' => $recovery ? $initial : null, 'recovery_until_at' => $recovery ? now() : null]);
    $before = $sync->fresh()->getAttributes();
    app(MaddraxikonStreakSettings::class)->activate();
    expect($sync->fresh()->initial_watermark_at_epoch)->toBe($initial->timestamp)
        ->and($sync->fresh()->watermark_at_epoch)->toBe($recovery ? null : $initial->timestamp)
        ->and($sync->fresh()->last_succeeded_at)->toBeNull();
    if ($recovery) {
        expect($sync->fresh()->getAttributes())->toBe($before);
    }
})->with('MaddraxikonStreakActivationTest.unfinishedImportStates');
test('invalid activation configuration rolls back all bootstrap writes', function (): void {
    config(['maddraxikon.streaks.starts_at' => 'tomorrow']);
    $this->expectException(\LogicException::class);
    try {
        app(MaddraxikonStreakSettings::class)->activate();
    } finally {
        $this->assertDatabaseCount('maddraxikon_streak_runs', 0);
        $this->assertDatabaseCount('maddraxikon_sync_states', 0);
    }
});
