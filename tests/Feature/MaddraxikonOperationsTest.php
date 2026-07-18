<?php

namespace Tests\Feature;

use App\Console\Kernel;
use App\Enums\MaddraxikonContributionStatus;
use App\Enums\MaddraxikonRewardEventStatus;
use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonContribution;
use App\Models\MaddraxikonRewardEvent;
use App\Models\MaddraxikonSyncState;
use App\Services\Maddraxikon\MaddraxikonContributionImporter;
use App\Services\Maddraxikon\MaddraxikonMonitoring;
use App\Services\Maddraxikon\MaddraxikonNamespaceHealthService;
use App\Services\Maddraxikon\MaddraxikonRewardService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class MaddraxikonOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_command_runs_importer_and_forwards_force_option(): void
    {
        $importer = Mockery::mock(MaddraxikonContributionImporter::class);
        $importer->expects('sync')->with(true)->once()->andReturn(7);
        $this->app->instance(MaddraxikonContributionImporter::class, $importer);

        $this->artisan('maddraxikon:sync', ['--force' => true])
            ->expectsOutput('Importierte Maddraxikon-Beiträge: 7')
            ->assertSuccessful();
    }

    public function test_recovery_command_uses_alarm_window_with_explicit_yes(): void
    {
        $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
        $this->travelTo($now);
        $from = $now->subDays(10);
        $until = $now->subHour();
        MaddraxikonSyncState::factory()->create([
            'wiki_key' => 'maddraxikon-de',
            'initial_watermark_at' => $now->subMonth()->setTimezone(config('app.timezone')),
            'watermark_at' => $from->setTimezone(config('app.timezone')),
            'recovery_required_at' => $now->subHour()->setTimezone(config('app.timezone')),
            'recovery_from_at' => $from->setTimezone(config('app.timezone')),
            'recovery_until_at' => $until->setTimezone(config('app.timezone')),
        ]);

        $importer = Mockery::mock(MaddraxikonContributionImporter::class);
        $importer->expects('recover')
            ->withArgs(fn (
                mixed $actualFrom,
                mixed $actualUntil
            ): bool => $actualFrom->equalTo($from)
                && $actualUntil->equalTo($until))
            ->once()
            ->andReturn(7);
        $this->app->instance(MaddraxikonContributionImporter::class, $importer);

        $this->artisan('maddraxikon:recover', ['--yes' => true])
            ->expectsOutput(sprintf(
                'Recovery abgeschlossen: 7 Maddraxikon-Beiträge importiert (%s bis %s).',
                $from->toIso8601String(),
                $until->toIso8601String()
            ))
            ->assertSuccessful();
    }

    public function test_recovery_command_requires_confirmation_without_yes(): void
    {
        $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
        $this->travelTo($now);
        $from = $now->subDays(2);
        $until = $now->subHour();
        MaddraxikonSyncState::factory()->create([
            'wiki_key' => 'maddraxikon-de',
            'initial_watermark_at' => $now->subMonth()->setTimezone(config('app.timezone')),
            'watermark_at' => $from->setTimezone(config('app.timezone')),
            'recovery_required_at' => $now->subHour()->setTimezone(config('app.timezone')),
            'recovery_from_at' => $from->setTimezone(config('app.timezone')),
            'recovery_until_at' => $until->setTimezone(config('app.timezone')),
        ]);

        $importer = Mockery::mock(MaddraxikonContributionImporter::class);
        $importer->shouldNotReceive('recover');
        $this->app->instance(MaddraxikonContributionImporter::class, $importer);
        $question = sprintf(
            'Recovery für maddraxikon-de von %s bis %s wirklich ausführen?',
            $from->toIso8601String(),
            $until->toIso8601String()
        );

        $this->artisan('maddraxikon:recover')
            ->expectsConfirmation($question, 'no')
            ->expectsOutput('Recovery abgebrochen.')
            ->assertFailed();
    }

    public function test_recovery_command_rejects_oversized_and_skipping_windows(): void
    {
        $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
        $this->travelTo($now);
        config(['maddraxikon.sync.recovery_max_window_days' => 7]);
        $from = $now->subDays(10);
        MaddraxikonSyncState::factory()->create([
            'wiki_key' => 'maddraxikon-de',
            'initial_watermark_at' => $now->subMonth()->setTimezone(config('app.timezone')),
            'watermark_at' => $from->setTimezone(config('app.timezone')),
            'recovery_required_at' => $now->subHour()->setTimezone(config('app.timezone')),
            'recovery_from_at' => $from->setTimezone(config('app.timezone')),
            'recovery_until_at' => $now->subHour()->setTimezone(config('app.timezone')),
        ]);

        $importer = Mockery::mock(MaddraxikonContributionImporter::class);
        $importer->shouldNotReceive('recover');
        $this->app->instance(MaddraxikonContributionImporter::class, $importer);

        $this->artisan('maddraxikon:recover', [
            '--from' => $from->toIso8601String(),
            '--until' => $now->subDay()->toIso8601String(),
            '--yes' => true,
        ])
            ->expectsOutput(
                'Ein Recovery-Lauf darf höchstens 7 Tage umfassen. Bitte das Fenster lückenlos aufteilen.'
            )
            ->assertFailed();

        config(['maddraxikon.sync.recovery_max_window_days' => 90]);

        $this->artisan('maddraxikon:recover', [
            '--from' => $from->addDay()->toIso8601String(),
            '--until' => $now->subHour()->toIso8601String(),
            '--yes' => true,
        ])
            ->expectsOutput(
                'Das Fenster muss exakt am Anfang der offenen Lücke beginnen.'
            )
            ->assertFailed();
    }

    public function test_recovery_command_rejects_relative_and_offsetless_timestamps(): void
    {
        $now = CarbonImmutable::parse('2026-07-18T12:00:00Z');
        $this->travelTo($now);

        MaddraxikonSyncState::factory()->create([
            'wiki_key' => 'maddraxikon-de',
            'initial_watermark_at' => $now->subMonth(),
            'watermark_at' => $now->subDays(2),
        ]);

        $importer = Mockery::mock(MaddraxikonContributionImporter::class);
        $importer->shouldNotReceive('recover');
        $this->app->instance(MaddraxikonContributionImporter::class, $importer);

        $this->artisan('maddraxikon:recover', [
            '--from' => 'yesterday',
            '--until' => '2026-07-18T11:00:00Z',
            '--yes' => true,
        ])
            ->expectsOutput(
                '--from muss ISO-8601 mit Sekunden und Z oder explizitem Offset sein.'
            )
            ->assertFailed();

        $this->artisan('maddraxikon:recover', [
            '--from' => '2026-07-17T11:00:00',
            '--until' => '2026-07-18T11:00:00Z',
            '--yes' => true,
        ])
            ->expectsOutput(
                '--from muss ISO-8601 mit Sekunden und Z oder explizitem Offset sein.'
            )
            ->assertFailed();
    }

    public function test_recovery_command_rejects_valid_window_without_open_alarm(): void
    {
        $now = CarbonImmutable::parse('2026-07-18T12:00:00Z');
        $this->travelTo($now);
        $from = $now->subDays(2);
        $until = $now->subDay();
        MaddraxikonSyncState::factory()->create([
            'wiki_key' => 'maddraxikon-de',
            'initial_watermark_at' => $now->subMonth(),
            'watermark_at' => $from,
        ]);
        $importer = Mockery::mock(MaddraxikonContributionImporter::class);
        $importer->shouldNotReceive('recover');
        $this->app->instance(MaddraxikonContributionImporter::class, $importer);

        $this->artisan('maddraxikon:recover', [
            '--from' => $from->toIso8601String(),
            '--until' => $until->toIso8601String(),
            '--yes' => true,
        ])
            ->expectsOutput(
                'Recovery ist nur bei einem offenen Recovery-Alarm zulässig.'
            )
            ->assertFailed();

        $this->assertDatabaseCount('maddraxikon_contributions', 0);
    }

    public function test_evaluate_command_runs_reward_service_without_force_by_default(): void
    {
        $rewardService = Mockery::mock(MaddraxikonRewardService::class);
        $rewardService->expects('evaluate')->with(false)->once()->andReturn(3);
        $this->app->instance(MaddraxikonRewardService::class, $rewardService);

        $this->artisan('maddraxikon:evaluate')
            ->expectsOutput('Ausgewertete Maddraxikon-Quellen: 3')
            ->assertSuccessful();
    }

    public function test_status_reports_database_state_and_healthy_namespaces(): void
    {
        config([
            'maddraxikon.wiki_key' => 'test-wiki',
            'maddraxikon.features.linking_enabled' => true,
            'maddraxikon.features.sync_enabled' => true,
            'maddraxikon.features.awards_enabled' => false,
        ]);
        MaddraxikonSyncState::factory()->create([
            'wiki_key' => 'test-wiki',
            'consecutive_failures' => 2,
            'last_error' => 'vorheriger Testfehler',
        ]);
        $link = MaddraxikonAccountLink::factory()->create([
            'wiki_key' => 'test-wiki',
        ]);
        $identity = [
            'wiki_key' => 'test-wiki',
            'account_link_id' => $link->id,
            'user_id' => $link->user_id,
            'wiki_user_id' => $link->wiki_user_id,
            'wiki_username' => $link->wiki_username,
        ];
        MaddraxikonContribution::factory()->create([
            ...$identity,
            'eligible_after' => now()->subMinute(),
        ]);
        MaddraxikonContribution::factory()->create([
            ...$identity,
            'eligible_after' => now()->addMinute(),
        ]);
        MaddraxikonContribution::factory()->create([
            ...$identity,
            'status' => MaddraxikonContributionStatus::Qualified,
        ]);

        $health = Mockery::mock(MaddraxikonNamespaceHealthService::class);
        $health->expects('check')->once()->andReturn([
            'healthy' => true,
            'expected' => [],
            'actual' => [],
            'missing' => [],
            'mismatched' => [],
        ]);
        $this->app->instance(MaddraxikonNamespaceHealthService::class, $health);
        Cache::forever(
            MaddraxikonMonitoring::SCHEDULER_HEARTBEAT_CACHE_KEY,
            now('UTC')->toIso8601String()
        );

        $this->artisan('maddraxikon:status')
            ->expectsOutput('Letzter Sync-Fehler: vorheriger Testfehler')
            ->expectsOutput('Namespace-Prüfung erfolgreich.')
            ->assertSuccessful();
    }

    public function test_status_can_skip_api_check(): void
    {
        $health = Mockery::mock(MaddraxikonNamespaceHealthService::class);
        $health->shouldNotReceive('check');
        $this->app->instance(MaddraxikonNamespaceHealthService::class, $health);

        $this->artisan('maddraxikon:status', ['--skip-api' => true])
            ->expectsOutput('Namespace-Prüfung übersprungen.')
            ->assertSuccessful();
    }

    public function test_status_fails_if_awards_are_enabled_without_import(): void
    {
        config([
            'maddraxikon.features.sync_enabled' => false,
            'maddraxikon.features.awards_enabled' => true,
        ]);
        Cache::forever(
            MaddraxikonMonitoring::SCHEDULER_HEARTBEAT_CACHE_KEY,
            now('UTC')->toIso8601String()
        );

        $this->artisan('maddraxikon:status', ['--skip-api' => true])
            ->expectsOutput(
                'Betriebsalarm: Baxx-Auszahlungen sind aktiviert, obwohl der Import deaktiviert ist.'
            )
            ->assertFailed();
    }

    public function test_heartbeat_command_records_scheduler_liveness(): void
    {
        $this->assertNull(
            Cache::get(MaddraxikonMonitoring::SCHEDULER_HEARTBEAT_CACHE_KEY)
        );

        $this->artisan('maddraxikon:heartbeat')
            ->expectsOutputToContain('Maddraxikon-Scheduler-Lebenszeichen:')
            ->assertSuccessful();

        $this->assertIsString(
            Cache::get(MaddraxikonMonitoring::SCHEDULER_HEARTBEAT_CACHE_KEY)
        );
    }

    public function test_status_fails_for_stale_pending_and_technical_reward_errors(): void
    {
        config([
            'maddraxikon.features.awards_enabled' => true,
            'maddraxikon.monitoring.pending_stale_hours' => 26,
        ]);
        Cache::forever(
            MaddraxikonMonitoring::SCHEDULER_HEARTBEAT_CACHE_KEY,
            now('UTC')->toIso8601String()
        );
        $link = MaddraxikonAccountLink::factory()->create();
        MaddraxikonContribution::factory()->create([
            'wiki_key' => 'maddraxikon-de',
            'account_link_id' => $link->id,
            'user_id' => $link->user_id,
            'wiki_user_id' => $link->wiki_user_id,
            'wiki_username' => $link->wiki_username,
            'occurred_at' => now()->subHours(27),
            'eligible_after' => now()->subHours(3),
            'last_evaluation_error' => 'API timeout',
            'last_evaluation_error_at' => now()->subMinute(),
        ]);

        $this->artisan('maddraxikon:status', ['--skip-api' => true])
            ->expectsOutput(
                'Betriebsalarm: 1 Beiträge warten länger als vorgesehen auf die Auswertung.'
            )
            ->expectsOutput(
                'Betriebsalarm: 1 Beiträge haben einen technischen Auswertungsfehler.'
            )
            ->assertFailed();
    }

    public function test_status_reports_reward_ratio_reasons_and_daily_caps(): void
    {
        $user = MaddraxikonAccountLink::factory()->create()->user;
        MaddraxikonRewardEvent::factory()->create([
            'user_id' => $user->id,
            'status' => MaddraxikonRewardEventStatus::Awarded,
            'status_reason' => 'qualified',
            'candidate_points' => 3,
            'awarded_points' => 1,
            'capped_points' => 2,
        ]);
        MaddraxikonRewardEvent::factory()->create([
            'user_id' => $user->id,
            'status' => MaddraxikonRewardEventStatus::Rejected,
            'status_reason' => 'article_too_small',
        ]);
        MaddraxikonRewardEvent::factory()->create([
            'user_id' => $user->id,
            'status' => MaddraxikonRewardEventStatus::EvaluatedNoAward,
            'status_reason' => 'sequence_not_payable',
        ]);

        $exitCode = Artisan::call('maddraxikon:status', [
            '--skip-api' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('1:1', $output);
        $this->assertStringContainsString(
            '1 Ereignisse / 2 Baxx',
            $output
        );
        $this->assertStringContainsString('article_too_small: 1', $output);
    }

    public function test_status_fails_if_database_queue_metrics_are_unavailable(): void
    {
        config([
            'maddraxikon.features.awards_enabled' => true,
            'queue.default' => 'database',
            'queue.connections.database.table' => 'missing_jobs_table',
            'queue.failed.table' => 'missing_failed_jobs_table',
        ]);
        Cache::forever(
            MaddraxikonMonitoring::SCHEDULER_HEARTBEAT_CACHE_KEY,
            now('UTC')->toIso8601String()
        );

        $this->artisan('maddraxikon:status', ['--skip-api' => true])
            ->expectsOutput(
                'Betriebsalarm: Die Maddraxikon-Queue-Metrik ist nicht verfügbar.'
            )
            ->assertFailed();
    }

    public function test_status_fails_for_an_old_waiting_maddraxikon_job(): void
    {
        $now = CarbonImmutable::parse('2026-07-18T12:00:00Z');
        $this->travelTo($now);
        config([
            'maddraxikon.features.sync_enabled' => true,
            'maddraxikon.features.awards_enabled' => false,
            'maddraxikon.monitoring.queue_oldest_minutes' => 30,
            'queue.default' => 'database',
        ]);
        MaddraxikonSyncState::factory()->create([
            'wiki_key' => 'maddraxikon-de',
            'last_succeeded_at' => $now,
        ]);
        Cache::forever(
            MaddraxikonMonitoring::SCHEDULER_HEARTBEAT_CACHE_KEY,
            $now->toIso8601String()
        );
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{"displayName":"App\\\\Jobs\\\\SyncMaddraxikonContributions"}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => $now->subMinutes(31)->timestamp,
            'created_at' => $now->subMinutes(31)->timestamp,
        ]);

        $this->artisan('maddraxikon:status', ['--skip-api' => true])
            ->expectsOutput(
                'Betriebsalarm: Der älteste wartende Maddraxikon-Job ist 31 Minuten alt.'
            )
            ->assertFailed();
    }

    public function test_status_reports_recovery_alarm_as_failure_without_api_check(): void
    {
        $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
        MaddraxikonSyncState::factory()->create([
            'wiki_key' => 'maddraxikon-de',
            'recovery_required_at' => $now->setTimezone(config('app.timezone')),
            'recovery_from_at' => $now->subDays(31)->setTimezone(config('app.timezone')),
            'recovery_until_at' => $now->setTimezone(config('app.timezone')),
        ]);
        $health = Mockery::mock(MaddraxikonNamespaceHealthService::class);
        $health->shouldNotReceive('check');
        $this->app->instance(MaddraxikonNamespaceHealthService::class, $health);

        $this->artisan('maddraxikon:status', ['--skip-api' => true])
            ->expectsOutput(
                'RecentChanges kann die offene Lücke nicht mehr sicher abdecken. Führen Sie maddraxikon:recover bewusst aus; die Watermark wurde nicht übersprungen.'
            )
            ->assertFailed();
    }

    public function test_status_fails_for_missing_or_renamed_namespaces(): void
    {
        $health = Mockery::mock(MaddraxikonNamespaceHealthService::class);
        $health->expects('check')->once()->andReturn([
            'healthy' => false,
            'expected' => [],
            'actual' => [],
            'missing' => [420 => 'GeoJson'],
            'mismatched' => [
                10 => [
                    'expected' => 'Vorlage',
                    'actual' => 'Template',
                ],
            ],
        ]);
        $this->app->instance(MaddraxikonNamespaceHealthService::class, $health);

        $this->artisan('maddraxikon:status')
            ->expectsOutput('Namensraum 420 (GeoJson) fehlt.')
            ->expectsOutput('Namensraum 10: erwartet „Vorlage“, erhalten „Template“.')
            ->assertFailed();
    }

    public function test_status_fails_cleanly_if_namespace_api_is_unavailable(): void
    {
        $health = Mockery::mock(MaddraxikonNamespaceHealthService::class);
        $health->expects('check')->once()->andThrow(
            new RuntimeException('nicht erreichbar')
        );
        $this->app->instance(MaddraxikonNamespaceHealthService::class, $health);

        $this->artisan('maddraxikon:status')
            ->expectsOutput('Namespace-Prüfung fehlgeschlagen: nicht erreichbar')
            ->assertFailed();
    }

    public function test_correction_audit_pruning_honours_retention_and_pretend_mode(): void
    {
        $now = CarbonImmutable::parse('2026-07-18T12:00:00Z');
        $this->travelTo($now);
        config([
            'maddraxikon.privacy.correction_audit_retention_days' => 365,
        ]);
        $base = [
            'actor_user_id' => 10,
            'affected_user_id' => 20,
            'wiki_key' => 'maddraxikon-de',
            'old_oauth_subject_hash' => str_repeat('a', 64),
            'old_wiki_user_id' => 7301,
            'old_wiki_username' => 'Archivierter Nutzer',
            'reason' => 'Dokumentierte Zuordnungskorrektur',
        ];

        DB::table('maddraxikon_account_link_corrections')->insert([
            [
                ...$base,
                'released_account_link_id' => 1001,
                'corrected_at' => $now->subDays(366)->format('Y-m-d H:i:s'),
            ],
            [
                ...$base,
                'released_account_link_id' => 1002,
                'corrected_at' => $now->subDays(364)->format('Y-m-d H:i:s'),
            ],
        ]);

        $this->artisan('maddraxikon:prune-audit', ['--pretend' => true])
            ->expectsOutput(
                '1 abgelaufene Maddraxikon-Korrekturprotokolle (Stichtag: '
                .$now->subDays(365)->setTimezone(config('app.timezone'))
                    ->format('Y-m-d H:i:s').').'
            )
            ->assertSuccessful();
        $this->assertDatabaseCount(
            'maddraxikon_account_link_corrections',
            2
        );

        $this->artisan('maddraxikon:prune-audit')
            ->expectsOutput(
                '1 abgelaufene Maddraxikon-Korrekturprotokolle geloescht.'
            )
            ->assertSuccessful();
        $this->assertDatabaseMissing(
            'maddraxikon_account_link_corrections',
            ['released_account_link_id' => 1001]
        );
        $this->assertDatabaseHas(
            'maddraxikon_account_link_corrections',
            ['released_account_link_id' => 1002]
        );
    }

    public function test_scheduler_queues_sync_and_evaluation_without_overlap(): void
    {
        $schedule = app(Schedule::class);
        $method = new ReflectionMethod(Kernel::class, 'schedule');
        $method->invoke(app(Kernel::class), $schedule);
        $events = collect($schedule->events())
            ->whereIn('description', [
                'maddraxikon:sync-job',
                'maddraxikon:evaluate-job',
            ])
            ->keyBy('description');

        $this->assertCount(2, $events);
        $this->assertSame(
            '*/15 * * * *',
            $events->get('maddraxikon:sync-job')->expression
        );
        $this->assertSame(
            '0 * * * *',
            $events->get('maddraxikon:evaluate-job')->expression
        );
        $this->assertTrue(
            $events->get('maddraxikon:sync-job')->withoutOverlapping
        );
        $this->assertTrue(
            $events->get('maddraxikon:evaluate-job')->withoutOverlapping
        );
        $this->assertFalse($events->get('maddraxikon:sync-job')->onOneServer);
        $this->assertFalse($events->get('maddraxikon:evaluate-job')->onOneServer);

        $heartbeat = collect($schedule->events())
            ->firstWhere('description', 'maddraxikon:scheduler-heartbeat');
        $this->assertNotNull($heartbeat);
        $this->assertSame('* * * * *', $heartbeat->expression);

        $prune = collect($schedule->events())
            ->firstWhere('description', 'maddraxikon:prune-audit');
        $this->assertNotNull($prune);
        $this->assertSame('30 3 1 * *', $prune->expression);
    }
}
