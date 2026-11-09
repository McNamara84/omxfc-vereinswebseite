<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MaddraxikonContributionStatus;
use App\Enums\MaddraxikonRewardEventStatus;
use App\Jobs\EvaluateMaddraxikonContributions;
use App\Mail\ReviewCommentNotification;
use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonContribution;
use App\Models\MaddraxikonRatingSyncState;
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
use RuntimeException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('sync command runs importer and forwards force option', function (): void {
    $importer = Mockery::mock(MaddraxikonContributionImporter::class);
    $importer->expects('sync')->with(true)->once()->andReturn(7);
    $this->app->instance(MaddraxikonContributionImporter::class, $importer);
    $this->artisan('maddraxikon:sync', ['--force' => true])->expectsOutput('Importierte Maddraxikon-Beiträge: 7')->assertSuccessful();
});

test('recovery command uses alarm window with explicit yes', function (): void {
    $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
    $this->travelTo($now);
    $from = $now->subDays(10);
    $until = $now->subHour();
    MaddraxikonSyncState::factory()->create(['wiki_key' => 'maddraxikon-de', 'initial_watermark_at' => $now->subMonth()->setTimezone(config('app.timezone')), 'watermark_at' => $from->setTimezone(config('app.timezone')), 'recovery_required_at' => $now->subHour()->setTimezone(config('app.timezone')), 'recovery_from_at' => $from->setTimezone(config('app.timezone')), 'recovery_until_at' => $until->setTimezone(config('app.timezone'))]);
    $importer = Mockery::mock(MaddraxikonContributionImporter::class);
    $importer->expects('recover')->withArgs(fn (mixed $actualFrom, mixed $actualUntil): bool => $actualFrom->equalTo($from) && $actualUntil->equalTo($until))->once()->andReturn(7);
    $this->app->instance(MaddraxikonContributionImporter::class, $importer);
    $this->artisan('maddraxikon:recover', ['--yes' => true])->expectsOutput(sprintf('Recovery abgeschlossen: 7 Maddraxikon-Beiträge importiert (%s bis %s).', $from->toIso8601String(), $until->toIso8601String()))->assertSuccessful();
});

test('recovery command requires confirmation without yes', function (): void {
    $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
    $this->travelTo($now);
    $from = $now->subDays(2);
    $until = $now->subHour();
    MaddraxikonSyncState::factory()->create(['wiki_key' => 'maddraxikon-de', 'initial_watermark_at' => $now->subMonth()->setTimezone(config('app.timezone')), 'watermark_at' => $from->setTimezone(config('app.timezone')), 'recovery_required_at' => $now->subHour()->setTimezone(config('app.timezone')), 'recovery_from_at' => $from->setTimezone(config('app.timezone')), 'recovery_until_at' => $until->setTimezone(config('app.timezone'))]);
    $importer = Mockery::mock(MaddraxikonContributionImporter::class);
    $importer->shouldNotReceive('recover');
    $this->app->instance(MaddraxikonContributionImporter::class, $importer);
    $question = sprintf('Recovery für maddraxikon-de von %s bis %s wirklich ausführen?', $from->toIso8601String(), $until->toIso8601String());
    $this->artisan('maddraxikon:recover')->expectsConfirmation($question, 'no')->expectsOutput('Recovery abgebrochen.')->assertFailed();
});

test('recovery command rejects oversized and skipping windows', function (): void {
    $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
    $this->travelTo($now);
    config(['maddraxikon.sync.recovery_max_window_days' => 7]);
    $from = $now->subDays(10);
    MaddraxikonSyncState::factory()->create(['wiki_key' => 'maddraxikon-de', 'initial_watermark_at' => $now->subMonth()->setTimezone(config('app.timezone')), 'watermark_at' => $from->setTimezone(config('app.timezone')), 'recovery_required_at' => $now->subHour()->setTimezone(config('app.timezone')), 'recovery_from_at' => $from->setTimezone(config('app.timezone')), 'recovery_until_at' => $now->subHour()->setTimezone(config('app.timezone'))]);
    $importer = Mockery::mock(MaddraxikonContributionImporter::class);
    $importer->shouldNotReceive('recover');
    $this->app->instance(MaddraxikonContributionImporter::class, $importer);
    $this->artisan('maddraxikon:recover', ['--from' => $from->toIso8601String(), '--until' => $now->subDay()->toIso8601String(), '--yes' => true])->expectsOutput('Ein Recovery-Lauf darf höchstens 7 Tage umfassen. Bitte das Fenster lückenlos aufteilen.')->assertFailed();
    config(['maddraxikon.sync.recovery_max_window_days' => 90]);
    $this->artisan('maddraxikon:recover', ['--from' => $from->addDay()->toIso8601String(), '--until' => $now->subHour()->toIso8601String(), '--yes' => true])->expectsOutput('Das Fenster muss exakt am Anfang der offenen Lücke beginnen.')->assertFailed();
});

test('recovery command rejects relative and offsetless timestamps', function (): void {
    $now = CarbonImmutable::parse('2026-07-18T12:00:00Z');
    $this->travelTo($now);
    MaddraxikonSyncState::factory()->create(['wiki_key' => 'maddraxikon-de', 'initial_watermark_at' => $now->subMonth(), 'watermark_at' => $now->subDays(2)]);
    $importer = Mockery::mock(MaddraxikonContributionImporter::class);
    $importer->shouldNotReceive('recover');
    $this->app->instance(MaddraxikonContributionImporter::class, $importer);
    $this->artisan('maddraxikon:recover', ['--from' => 'yesterday', '--until' => '2026-07-18T11:00:00Z', '--yes' => true])->expectsOutput('--from muss ISO-8601 mit Sekunden und Z oder explizitem Offset sein.')->assertFailed();
    $this->artisan('maddraxikon:recover', ['--from' => '2026-07-17T11:00:00', '--until' => '2026-07-18T11:00:00Z', '--yes' => true])->expectsOutput('--from muss ISO-8601 mit Sekunden und Z oder explizitem Offset sein.')->assertFailed();
});

test('recovery command rejects valid window without open alarm', function (): void {
    $now = CarbonImmutable::parse('2026-07-18T12:00:00Z');
    $this->travelTo($now);
    $from = $now->subDays(2);
    $until = $now->subDay();
    MaddraxikonSyncState::factory()->create(['wiki_key' => 'maddraxikon-de', 'initial_watermark_at' => $now->subMonth(), 'watermark_at' => $from]);
    $importer = Mockery::mock(MaddraxikonContributionImporter::class);
    $importer->shouldNotReceive('recover');
    $this->app->instance(MaddraxikonContributionImporter::class, $importer);
    $this->artisan('maddraxikon:recover', ['--from' => $from->toIso8601String(), '--until' => $until->toIso8601String(), '--yes' => true])->expectsOutput('Recovery ist nur bei einem offenen Recovery-Alarm zulässig.')->assertFailed();
    $this->assertDatabaseCount('maddraxikon_contributions', 0);
});

test('evaluate command runs reward service without force by default', function (): void {
    $rewardService = Mockery::mock(MaddraxikonRewardService::class);
    $rewardService->expects('evaluate')->with(false)->once()->andReturn(3);
    $this->app->instance(MaddraxikonRewardService::class, $rewardService);
    $this->artisan('maddraxikon:evaluate')->expectsOutput('Ausgewertete Maddraxikon-Quellen: 3')->assertSuccessful();
});

test('status reports database state and healthy namespaces', function (): void {
    config(['maddraxikon.wiki_key' => 'test-wiki', 'maddraxikon.features.linking_enabled' => true, 'maddraxikon.features.sync_enabled' => true, 'maddraxikon.features.awards_enabled' => false]);
    MaddraxikonSyncState::factory()->create(['wiki_key' => 'test-wiki', 'consecutive_failures' => 2, 'last_error' => 'vorheriger Testfehler']);
    $link = MaddraxikonAccountLink::factory()->create(['wiki_key' => 'test-wiki']);
    $identity = ['wiki_key' => 'test-wiki', 'account_link_id' => $link->id, 'user_id' => $link->user_id, 'wiki_user_id' => $link->wiki_user_id, 'wiki_username' => $link->wiki_username];
    MaddraxikonContribution::factory()->create([...$identity, 'eligible_after' => now()->subMinute()]);
    MaddraxikonContribution::factory()->create([...$identity, 'eligible_after' => now()->addMinute()]);
    MaddraxikonContribution::factory()->create([...$identity, 'status' => MaddraxikonContributionStatus::Qualified]);
    $health = Mockery::mock(MaddraxikonNamespaceHealthService::class);
    $health->expects('check')->once()->andReturn(['healthy' => true, 'expected' => [], 'actual' => [], 'missing' => [], 'mismatched' => []]);
    $this->app->instance(MaddraxikonNamespaceHealthService::class, $health);
    Cache::forever(MaddraxikonMonitoring::SCHEDULER_HEARTBEAT_CACHE_KEY, now('UTC')->toIso8601String());
    $this->artisan('maddraxikon:status')->expectsOutput('Letzter Sync-Fehler: vorheriger Testfehler')->expectsOutput('Namespace-Prüfung erfolgreich.')->assertSuccessful();
});

test('status can skip api check', function (): void {
    $health = Mockery::mock(MaddraxikonNamespaceHealthService::class);
    $health->shouldNotReceive('check');
    $this->app->instance(MaddraxikonNamespaceHealthService::class, $health);
    $this->artisan('maddraxikon:status', ['--skip-api' => true])->expectsOutput('Namespace-Prüfung übersprungen.')->assertSuccessful();
});

test('status reports rating sync health and operational alarms', function (): void {
    config(['maddraxikon.wiki_key' => 'test-wiki', 'maddraxikon.features.sync_enabled' => false, 'maddraxikon.features.awards_enabled' => false, 'maddraxikon.features.ratings_enabled' => true, 'maddraxikon.ratings.stale_after_minutes' => 60, 'maddraxikon.monitoring.consecutive_failure_limit' => 3]);
    MaddraxikonRatingSyncState::factory()->create(['wiki_key' => 'test-wiki', 'last_succeeded_at' => now()->subMinutes(61), 'last_error_category' => 'PDOException', 'consecutive_failures' => 3]);
    Cache::forever(MaddraxikonMonitoring::SCHEDULER_HEARTBEAT_CACHE_KEY, now('UTC')->toIso8601String());
    $this->artisan('maddraxikon:status', ['--skip-api' => true])->expectsOutput('Letzter Bewertungssync-Fehlertyp: PDOException')->expectsOutput('Betriebsalarm: Der letzte erfolgreiche Bewertungssync ist 61 Minuten alt.')->expectsOutput('Betriebsalarm: Der Bewertungssync ist 3-mal in Folge fehlgeschlagen.')->assertFailed();
});

test('status fails if awards are enabled without import', function (): void {
    config(['maddraxikon.features.sync_enabled' => false, 'maddraxikon.features.awards_enabled' => true]);
    Cache::forever(MaddraxikonMonitoring::SCHEDULER_HEARTBEAT_CACHE_KEY, now('UTC')->toIso8601String());
    $this->artisan('maddraxikon:status', ['--skip-api' => true])->expectsOutput('Betriebsalarm: Baxx-Auszahlungen sind aktiviert, obwohl der Import deaktiviert ist.')->assertFailed();
});

test('heartbeat command records scheduler liveness', function (): void {
    expect(Cache::get(MaddraxikonMonitoring::SCHEDULER_HEARTBEAT_CACHE_KEY))->toBeNull();
    $this->artisan('maddraxikon:heartbeat')->expectsOutputToContain('Maddraxikon-Scheduler-Lebenszeichen:')->assertSuccessful();
    expect(Cache::get(MaddraxikonMonitoring::SCHEDULER_HEARTBEAT_CACHE_KEY))->toBeString();
});

test('status fails for stale pending and technical reward errors', function (): void {
    config(['maddraxikon.features.awards_enabled' => true, 'maddraxikon.monitoring.pending_stale_hours' => 26]);
    Cache::forever(MaddraxikonMonitoring::SCHEDULER_HEARTBEAT_CACHE_KEY, now('UTC')->toIso8601String());
    $link = MaddraxikonAccountLink::factory()->create();
    MaddraxikonContribution::factory()->create(['wiki_key' => 'maddraxikon-de', 'account_link_id' => $link->id, 'user_id' => $link->user_id, 'wiki_user_id' => $link->wiki_user_id, 'wiki_username' => $link->wiki_username, 'occurred_at' => now()->subHours(27), 'eligible_after' => now()->subHours(3), 'last_evaluation_error' => 'API timeout', 'last_evaluation_error_at' => now()->subMinute()]);
    $this->artisan('maddraxikon:status', ['--skip-api' => true])->expectsOutput('Betriebsalarm: 1 Beiträge warten länger als vorgesehen auf die Auswertung.')->expectsOutput('Betriebsalarm: 1 Beiträge haben einen technischen Auswertungsfehler.')->assertFailed();
});

test('status reports reward ratio reasons and daily caps', function (): void {
    $user = MaddraxikonAccountLink::factory()->create()->user;
    MaddraxikonRewardEvent::factory()->create(['user_id' => $user->id, 'status' => MaddraxikonRewardEventStatus::Awarded, 'status_reason' => 'qualified', 'candidate_points' => 3, 'awarded_points' => 1, 'capped_points' => 2]);
    MaddraxikonRewardEvent::factory()->create(['user_id' => $user->id, 'status' => MaddraxikonRewardEventStatus::Rejected, 'status_reason' => 'article_too_small']);
    MaddraxikonRewardEvent::factory()->create(['user_id' => $user->id, 'status' => MaddraxikonRewardEventStatus::EvaluatedNoAward, 'status_reason' => 'sequence_not_payable']);
    $exitCode = Artisan::call('maddraxikon:status', ['--skip-api' => true]);
    $output = Artisan::output();
    expect($exitCode)->toBe(0)
        ->and($output)->toContain('1:1')
        ->toContain('1 Ereignisse / 2 Baxx')
        ->toContain('article_too_small: 1');
});

test('status fails if database queue metrics are unavailable', function (): void {
    config(['maddraxikon.features.awards_enabled' => true, 'queue.default' => 'database', 'queue.connections.database.table' => 'missing_jobs_table', 'queue.failed.table' => 'missing_failed_jobs_table']);
    Cache::forever(MaddraxikonMonitoring::SCHEDULER_HEARTBEAT_CACHE_KEY, now('UTC')->toIso8601String());
    $this->artisan('maddraxikon:status', ['--skip-api' => true])->expectsOutput('Betriebsalarm: Die Maddraxikon-Queue-Metrik ist nicht verfügbar.')->assertFailed();
});

test('status reports total queue size separately from maddraxikon jobs', function (): void {
    config(['queue.default' => 'database']);
    $now = now()->timestamp;
    DB::table('jobs')->insert([['queue' => 'default', 'payload' => json_encode(['displayName' => EvaluateMaddraxikonContributions::class], JSON_THROW_ON_ERROR), 'attempts' => 0, 'reserved_at' => null, 'available_at' => $now, 'created_at' => $now], ['queue' => 'mail', 'payload' => json_encode(['displayName' => 'App\Jobs\SendNewsletter'], JSON_THROW_ON_ERROR), 'attempts' => 0, 'reserved_at' => null, 'available_at' => $now, 'created_at' => $now]]);
    $exitCode = Artisan::call('maddraxikon:status', ['--skip-api' => true]);
    $output = Artisan::output();
    expect($exitCode)->toBe(0)
        ->and($output)->toMatch('/Gesamter Queue-Rückstau\s+\|\s+2/')
        ->toMatch('/Maddraxikon-Queue-Rückstau\s+\|\s+1/');
});

test('status fails for an old waiting maddraxikon job', function (): void {
    $now = CarbonImmutable::parse('2026-07-18T12:00:00Z');
    $this->travelTo($now);
    config(['maddraxikon.features.sync_enabled' => true, 'maddraxikon.features.awards_enabled' => false, 'maddraxikon.monitoring.queue_oldest_minutes' => 30, 'queue.default' => 'database']);
    MaddraxikonSyncState::factory()->create(['wiki_key' => 'maddraxikon-de', 'last_succeeded_at' => $now]);
    Cache::forever(MaddraxikonMonitoring::SCHEDULER_HEARTBEAT_CACHE_KEY, $now->toIso8601String());
    DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{"displayName":"App\\\\Jobs\\\\SyncMaddraxikonContributions"}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => $now->subMinutes(31)->timestamp, 'created_at' => $now->subMinutes(31)->timestamp]);
    $this->artisan('maddraxikon:status', ['--skip-api' => true])->expectsOutput('Betriebsalarm: Der älteste wartende Maddraxikon-Job ist 31 Minuten alt.')->assertFailed();
});

test('status ignores unrelated jobs that only mention maddraxikon in their payload', function (): void {
    $now = CarbonImmutable::parse('2026-07-18T12:00:00Z');
    $this->travelTo($now);
    config(['maddraxikon.features.sync_enabled' => true, 'maddraxikon.features.awards_enabled' => false, 'queue.default' => 'database']);
    MaddraxikonSyncState::factory()->create(['wiki_key' => 'maddraxikon-de', 'last_succeeded_at' => $now]);
    Cache::forever(MaddraxikonMonitoring::SCHEDULER_HEARTBEAT_CACHE_KEY, $now->toIso8601String());
    $mailPayload = json_encode(['displayName' => ReviewCommentNotification::class, 'data' => ['recipient' => 'info@maddraxikon.com']], JSON_THROW_ON_ERROR);
    DB::table('jobs')->insert(['queue' => 'default', 'payload' => $mailPayload, 'attempts' => 0, 'reserved_at' => null, 'available_at' => $now->subMinutes(31)->timestamp, 'created_at' => $now->subMinutes(31)->timestamp]);
    DB::table('failed_jobs')->insert(['uuid' => 'unrelated-maddraxikon-mail-job', 'connection' => 'database', 'queue' => 'default', 'payload' => $mailPayload, 'exception' => 'Test exception', 'failed_at' => $now]);
    $this->artisan('maddraxikon:status', ['--skip-api' => true])->doesntExpectOutputToContain('Maddraxikon-Jobs sind endgültig fehlgeschlagen.')->doesntExpectOutputToContain('Der älteste wartende Maddraxikon-Job ist')->assertSuccessful();
});

test('status counts only exact failed maddraxikon job classes', function (): void {
    $now = CarbonImmutable::parse('2026-07-18T12:00:00Z');
    $this->travelTo($now);
    config(['maddraxikon.features.sync_enabled' => true, 'maddraxikon.features.awards_enabled' => false, 'queue.default' => 'database']);
    MaddraxikonSyncState::factory()->create(['wiki_key' => 'maddraxikon-de', 'last_succeeded_at' => $now]);
    Cache::forever(MaddraxikonMonitoring::SCHEDULER_HEARTBEAT_CACHE_KEY, $now->toIso8601String());
    DB::table('failed_jobs')->insert([['uuid' => 'actual-maddraxikon-job', 'connection' => 'database', 'queue' => 'default', 'payload' => json_encode(['displayName' => EvaluateMaddraxikonContributions::class], JSON_THROW_ON_ERROR), 'exception' => 'Test exception', 'failed_at' => $now], ['uuid' => 'unrelated-mail-job', 'connection' => 'database', 'queue' => 'default', 'payload' => json_encode(['displayName' => ReviewCommentNotification::class, 'data' => ['recipient' => 'info@maddraxikon.com']], JSON_THROW_ON_ERROR), 'exception' => 'Test exception', 'failed_at' => $now]]);
    $this->artisan('maddraxikon:status', ['--skip-api' => true])->expectsOutput('Betriebsalarm: 1 Maddraxikon-Jobs sind endgültig fehlgeschlagen.')->assertFailed();
});

test('status reports recovery alarm as failure without api check', function (): void {
    $now = CarbonImmutable::parse('2026-07-18 12:00:00', 'UTC');
    MaddraxikonSyncState::factory()->create(['wiki_key' => 'maddraxikon-de', 'recovery_required_at' => $now->setTimezone(config('app.timezone')), 'recovery_from_at' => $now->subDays(31)->setTimezone(config('app.timezone')), 'recovery_until_at' => $now->setTimezone(config('app.timezone'))]);
    $health = Mockery::mock(MaddraxikonNamespaceHealthService::class);
    $health->shouldNotReceive('check');
    $this->app->instance(MaddraxikonNamespaceHealthService::class, $health);
    $this->artisan('maddraxikon:status', ['--skip-api' => true])->expectsOutput('RecentChanges kann die offene Lücke nicht mehr sicher abdecken. Führen Sie maddraxikon:recover bewusst aus; die Watermark wurde nicht übersprungen.')->assertFailed();
});

test('status fails for missing or renamed namespaces', function (): void {
    $health = Mockery::mock(MaddraxikonNamespaceHealthService::class);
    $health->expects('check')->once()->andReturn(['healthy' => false, 'expected' => [], 'actual' => [], 'missing' => [420 => 'GeoJson'], 'mismatched' => [10 => ['expected' => 'Vorlage', 'actual' => 'Template']]]);
    $this->app->instance(MaddraxikonNamespaceHealthService::class, $health);
    $this->artisan('maddraxikon:status')->expectsOutput('Namensraum 420 (GeoJson) fehlt.')->expectsOutput('Namensraum 10: erwartet „Vorlage“, erhalten „Template“.')->assertFailed();
});

test('status fails cleanly if namespace api is unavailable', function (): void {
    $health = Mockery::mock(MaddraxikonNamespaceHealthService::class);
    $health->expects('check')->once()->andThrow(new RuntimeException('nicht erreichbar'));
    $this->app->instance(MaddraxikonNamespaceHealthService::class, $health);
    $this->artisan('maddraxikon:status')->expectsOutput('Namespace-Prüfung fehlgeschlagen: nicht erreichbar')->assertFailed();
});

test('correction audit pruning honours retention and pretend mode', function (): void {
    $now = CarbonImmutable::parse('2026-07-18T12:00:00Z');
    $this->travelTo($now);
    config(['maddraxikon.privacy.correction_audit_retention_days' => 365]);
    $base = ['actor_user_id' => 10, 'affected_user_id' => 20, 'wiki_key' => 'maddraxikon-de', 'old_oauth_subject_hash' => str_repeat('a', 64), 'old_wiki_user_id' => 7301, 'old_wiki_username' => 'Archivierter Nutzer', 'reason' => 'Dokumentierte Zuordnungskorrektur'];
    DB::table('maddraxikon_account_link_corrections')->insert([[...$base, 'released_account_link_id' => 1001, 'corrected_at' => $now->subDays(366)->format('Y-m-d H:i:s')], [...$base, 'released_account_link_id' => 1002, 'corrected_at' => $now->subDays(364)->format('Y-m-d H:i:s')]]);
    $this->artisan('maddraxikon:prune-audit', ['--pretend' => true])->expectsOutput('1 abgelaufene Maddraxikon-Korrekturprotokolle (Stichtag: '.$now->subDays(365)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s').').')->assertSuccessful();
    $this->assertDatabaseCount('maddraxikon_account_link_corrections', 2);
    $this->artisan('maddraxikon:prune-audit')->expectsOutput('1 abgelaufene Maddraxikon-Korrekturprotokolle geloescht.')->assertSuccessful();
    $this->assertDatabaseMissing('maddraxikon_account_link_corrections', ['released_account_link_id' => 1001]);
    $this->assertDatabaseHas('maddraxikon_account_link_corrections', ['released_account_link_id' => 1002]);
});

test('application registers the complete scheduler configuration', function (): void {
    expect(Artisan::call('schedule:list', ['--no-ansi' => true]))->toBe(0)
        ->and(Artisan::output())->not->toContain('No scheduled tasks have been defined');
    $schedule = app(Schedule::class);
    $events = collect($schedule->events());
    $namedEvents = $events->whereIn('description', ['maddraxikon:prune-audit', 'maddraxikon:scheduler-heartbeat', 'maddraxikon:sync-job', 'maddraxikon:evaluate-job', 'maddraxikon:review-ratings-sync-job', 'cover-ratings:sync-job'])->keyBy('description');
    expect($namedEvents)->toHaveCount(6)
        ->and($namedEvents->get('maddraxikon:sync-job')->expression)->toBe('*/15 * * * *')
        ->and($namedEvents->get('maddraxikon:evaluate-job')->expression)->toBe('0 * * * *')
        ->and($namedEvents->get('maddraxikon:review-ratings-sync-job')->expression)->toBe('*/15 * * * *')
        ->and($namedEvents->get('cover-ratings:sync-job')->expression)->toBe('15 3 * * *')
        ->and($namedEvents->get('maddraxikon:sync-job')->withoutOverlapping)->toBeTrue()
        ->and($namedEvents->get('maddraxikon:evaluate-job')->withoutOverlapping)->toBeTrue()
        ->and($namedEvents->get('maddraxikon:review-ratings-sync-job')->withoutOverlapping)->toBeTrue()
        ->and($namedEvents->get('cover-ratings:sync-job')->withoutOverlapping)->toBeTrue()
        ->and($namedEvents->get('maddraxikon:sync-job')->expiresAt)->toBe(15)
        ->and($namedEvents->get('maddraxikon:evaluate-job')->expiresAt)->toBe(60)
        ->and($namedEvents->get('maddraxikon:review-ratings-sync-job')->expiresAt)->toBe(15)
        ->and($namedEvents->get('cover-ratings:sync-job')->expiresAt)->toBe(120)
        ->and($namedEvents->get('maddraxikon:sync-job')->onOneServer)->toBeFalse()
        ->and($namedEvents->get('maddraxikon:evaluate-job')->onOneServer)->toBeFalse()
        ->and($namedEvents->get('maddraxikon:review-ratings-sync-job')->onOneServer)->toBeFalse();
    $heartbeat = $namedEvents->get('maddraxikon:scheduler-heartbeat');
    expect($heartbeat->expression)->toBe('* * * * *');
    $prune = $namedEvents->get('maddraxikon:prune-audit');
    expect($prune->expression)->toBe('30 3 1 * *')
        ->and($prune->withoutOverlapping)->toBeTrue()
        ->and($prune->expiresAt)->toBe(30);
    foreach (['member-map:refresh' => '0 * * * *', 'polls:archive-ended' => '0 * * * *', 'database-maintenance:cleanup' => '0 0 * * *'] as $command => $expression) {
        $event = $events->first(static fn (object $event): bool => str_contains((string) ($event->command ?? ''), $command));
        expect($event)->not->toBeNull()
            ->and($event->expression)->toBe($expression);
    }
});
