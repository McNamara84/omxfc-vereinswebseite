<?php

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
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MaddraxikonStreakActivationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-10-24T12:00:00Z'));
        config(['maddraxikon.streaks.enabled' => true, 'maddraxikon.streaks.starts_at' => null]);
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_10_24_160000_activate_maddraxikon_streaks.php');
    }

    public function test_config_defaults_to_enabled_and_still_honors_explicit_false(): void
    {
        $key = 'MADDRAXIKON_STREAKS_ENABLED';
        $environment = $_ENV[$key] ?? null;
        $server = $_SERVER[$key] ?? null;
        $process = getenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
        putenv($key);
        try {
            $config = require config_path('maddraxikon.php');
            $this->assertTrue($config['streaks']['enabled']);
            $_ENV[$key] = 'false';
            $config = require config_path('maddraxikon.php');
            $this->assertFalse($config['streaks']['enabled']);
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
    }

    public function test_deployment_activates_panel_and_persists_an_immutable_start_without_env_date(): void
    {
        $epoch = now()->timestamp;
        $migration = $this->migration();
        $migration->up();
        $settings = app(MaddraxikonStreakSettings::class);
        $this->assertTrue($settings->enabled());
        $this->assertSame($epoch, $settings->start()->timestamp);
        $this->assertSame($epoch, MaddraxikonSyncState::query()->sole()->initial_watermark_at_epoch);
        $this->assertNull(MaddraxikonSyncState::query()->sole()->last_succeeded_at);

        $member = User::factory()->create();
        Team::membersTeam()->users()->attach($member, ['role' => Role::Mitglied->value]);
        $this->actingAs($member);
        Livewire::test(MaddraxikonStreakPanel::class)->assertSee('Maddraxikon-Serie');

        $this->travelTo(now()->addDays(2));
        $migration->down();
        $migration->up();
        $this->assertSame($epoch, app(MaddraxikonStreakSettings::class)->start()->timestamp);
        $this->assertDatabaseCount('maddraxikon_streak_runs', 1);
        $this->assertSame($epoch, MaddraxikonSyncState::query()->sole()->watermark_at_epoch);
    }

    public function test_existing_series_and_recovery_progress_are_preserved(): void
    {
        $start = CarbonImmutable::parse('2026-10-01T00:00:00+02:00')->timestamp;
        MaddraxikonStreakRun::query()->create(['wiki_key' => config('maddraxikon.wiki_key'), 'starts_at_epoch' => $start]);
        $sync = MaddraxikonSyncState::factory()->create([
            'initial_watermark_at' => '2026-09-01T00:00:00Z', 'watermark_at' => '2026-10-20T00:00:00Z',
            'recovery_required_at' => now(), 'recovery_from_at' => '2026-10-20T00:00:00Z', 'recovery_until_at' => now(),
        ]);
        $before = $sync->fresh()->getRawOriginal();
        $this->migration()->up();
        $this->assertSame($start, app(MaddraxikonStreakSettings::class)->start()->timestamp);
        $this->assertSame($before, $sync->fresh()->getRawOriginal());
    }

    public function test_explicitly_disabled_rollout_creates_no_activation_or_import_state(): void
    {
        config(['maddraxikon.streaks.enabled' => false]);
        $this->migration()->up();
        $this->assertFalse(app(MaddraxikonStreakSettings::class)->enabled());
        $this->assertNull(app(MaddraxikonStreakSettings::class)->start());
        $this->assertDatabaseCount('maddraxikon_streak_runs', 0);
        $this->assertDatabaseCount('maddraxikon_sync_states', 0);
    }

    public function test_explicit_start_survives_removing_the_optional_env_override(): void
    {
        config(['maddraxikon.streaks.starts_at' => '2026-11-01T00:00:00+01:00']);
        $this->migration()->up();
        config(['maddraxikon.streaks.starts_at' => '']);
        $this->assertSame('2026-10-31T23:00:00+00:00', app(MaddraxikonStreakSettings::class)->start()->toIso8601String());
        config(['maddraxikon.streaks.enabled' => false]);
        $this->travelTo(now()->addDays(20));
        config(['maddraxikon.streaks.enabled' => true]);
        $this->assertSame('2026-10-31T23:00:00+00:00', app(MaddraxikonStreakSettings::class)->start()->toIso8601String());
    }

    public function test_first_scheduled_import_covers_launch_but_never_awards_prelaunch_activity(): void
    {
        config(['maddraxikon.features.sync_enabled' => true, 'maddraxikon.features.awards_enabled' => true]);
        $this->migration()->up();
        $launch = CarbonImmutable::now();
        $member = User::factory()->create();
        Team::membersTeam()->users()->attach($member, ['role' => Role::Mitglied->value]);
        $link = MaddraxikonAccountLink::factory()->create(['user_id' => $member->id, 'verified_at' => now()->subDays(2)]);
        $changes = [];
        foreach ([-1, 0] as $index => $offset) {
            $changes[] = [
                'type' => 'edit', 'ns' => 0, 'title' => 'Starttest', 'pageid' => 42,
                'revid' => 100 + $index, 'old_revid' => 99, 'rcid' => 100 + $index,
                'user' => $link->wiki_username, 'userid' => $link->wiki_user_id,
                'oldlen' => 100, 'newlen' => 99, 'tags' => [],
                'timestamp' => $launch->addSeconds($offset)->utc()->format('Y-m-d\TH:i:s\Z'),
            ];
        }
        $api = Mockery::mock(MaddraxikonApiClient::class);
        $api->shouldReceive('recentChanges')->once()->withArgs(fn ($from, $until) => $from->lte($launch) && $until->gt($launch))->andReturn($changes);
        $api->shouldReceive('streakRevisionDetails')->once()->with([101])->andReturn([101 => ['exists' => true, 'page_id' => 42, 'user_id' => $link->wiki_user_id, 'tags' => []]]);
        $api->shouldReceive('streakPageDetails')->once()->with([42])->andReturn([42 => ['exists' => true]]);
        $this->app->instance(MaddraxikonApiClient::class, $api);
        $this->travelTo($launch->addMinutes(15));
        $this->assertSame(2, app(MaddraxikonContributionImporter::class)->sync());
        $this->assertNull(MaddraxikonContribution::query()->where('revision_id', 100)->sole()->streak_activity_date);
        $this->assertSame(0, app(MaddraxikonStreakService::class)->run());
        $this->travelTo($launch->addDay());
        $this->assertSame(1, app(MaddraxikonStreakService::class)->run());
        $this->assertSame(1, (int) UserPoint::query()->where('user_id', $member->id)->sum('points'));
    }

    #[DataProvider('changedConfigurationImports')]
    public function test_changed_configuration_preserves_rollout_imports_and_display(string $configuredStart, bool $recovery): void
    {
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
            $changes[] = [
                'type' => 'edit', 'ns' => 0, 'title' => 'Fester Serienstart', 'pageid' => 42,
                'revid' => 100 + $index, 'old_revid' => 99, 'parentid' => 99, 'rcid' => 100 + $index,
                'user' => $link->wiki_username, 'userid' => $link->wiki_user_id,
                'oldlen' => 100, 'newlen' => 99, 'size' => 99, 'sizediff' => -1, 'tags' => [],
                'timestamp' => $launch->addSeconds($offset)->utc()->format('Y-m-d\TH:i:s\Z'),
            ];
        }
        $api = Mockery::mock(MaddraxikonApiClient::class);
        $api->shouldReceive($recovery ? 'userContributions' : 'recentChanges')->once()->andReturn($changes);
        $api->shouldReceive('streakRevisionDetails')->once()->with([101, 102])->andReturn([
            101 => ['exists' => true, 'page_id' => 42, 'user_id' => $link->wiki_user_id, 'tags' => []],
            102 => ['exists' => true, 'page_id' => 42, 'user_id' => $link->wiki_user_id, 'tags' => []],
        ]);
        $api->shouldReceive('streakPageDetails')->once()->with([42])->andReturn([42 => ['exists' => true]]);
        $this->app->instance(MaddraxikonApiClient::class, $api);

        $importer = app(MaddraxikonContributionImporter::class);
        $this->assertSame(2, $recovery ? $importer->recover($launch, CarbonImmutable::now()) : $importer->sync());
        $this->assertSame([101, 102], MaddraxikonContribution::query()->orderBy('revision_id')->pluck('revision_id')->all());
        $this->assertSame(['2026-10-24'], MaddraxikonContribution::query()->distinct()->pluck('streak_activity_date')->all());
        $this->assertSame($launch->timestamp, app(MaddraxikonStreakSettings::class)->start()->timestamp);

        $this->actingAs($member);
        Livewire::test(MaddraxikonStreakPanel::class)
            ->assertSee('Maddraxikon-Serie')->assertSee('Aktivität erkannt – Bonus wird geprüft.')
            ->assertViewHas('streak', fn ($streak) => $streak['today_pending'] && ! $streak['delayed']);
        app(MaddraxikonMonitoring::class)->recordSchedulerHeartbeat();
        $this->assertSame(0, Artisan::call('maddraxikon:status', ['--skip-api' => true]));
        $this->assertMatchesRegularExpression('/\| Start\s+\| '.preg_quote($launch->utc()->toIso8601String(), '/').'\s+\|/', Artisan::output());

        $this->travelTo($launch->addDay()->addMinute());
        $sync->refresh()->update(['watermark_at' => now(), 'last_succeeded_at' => now()]);
        // Recovery retains its independent requirement for a verified historical bot status.
        $this->assertSame($recovery ? 0 : 1, app(MaddraxikonStreakService::class)->run());
        $this->assertSame($recovery ? 0 : 1, (int) UserPoint::query()->where('user_id', $member->id)->sum('points'));
        $this->migration()->up();
        $this->assertSame($launch->timestamp, MaddraxikonStreakRun::query()->sole()->starts_at_epoch);
    }

    public static function changedConfigurationImports(): array
    {
        return [
            'later start during normal import' => ['2026-11-01T00:00:00+01:00', false],
            'earlier start during normal import' => ['2026-10-23T12:00:00Z', false],
            'later start during recovery' => ['2026-11-01T00:00:00+01:00', true],
            'earlier start during recovery' => ['2026-10-23T12:00:00Z', true],
        ];
    }
}
