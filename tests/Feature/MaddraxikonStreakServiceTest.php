<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Exceptions\MaddraxikonApiException;
use App\Jobs\EvaluateMaddraxikonStreaks;
use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonContribution;
use App\Models\MaddraxikonRewardPolicy;
use App\Models\MaddraxikonStreakBooking;
use App\Models\MaddraxikonStreakDay;
use App\Models\MaddraxikonStreakState;
use App\Models\MaddraxikonSyncState;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPoint;
use App\Services\Baxx\BaxxBookingWriter;
use App\Services\Maddraxikon\AccountLinkService;
use App\Services\Maddraxikon\MaddraxikonApiClient;
use App\Services\Maddraxikon\MaddraxikonContributionImporter;
use App\Services\Maddraxikon\MaddraxikonIdentity;
use App\Services\Maddraxikon\MaddraxikonStreakService;
use App\Services\Maddraxikon\MaddraxikonStreakSettings;
use App\Services\Maddraxikon\MaddraxikonStreakSummary;
use App\Services\Maddraxikon\MaddraxikonStreakTracker;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Mockery;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(MaddraxikonStreakServiceTestFixtures::class);
dataset('MaddraxikonStreakServiceTest.disabledConfigurations', static fn (): array => [['maddraxikon.streaks.enabled', false], ['maddraxikon.features.awards_enabled', false], ['maddraxikon.features.sync_enabled', false]]);
dataset('MaddraxikonStreakServiceTest.reconciliationBatchSizes', static fn (): array => [[1], [10], [500]]);
dataset('MaddraxikonStreakServiceTest.historicalCorrections', static function (): array {
    $cases = [];
    foreach (['disconnected', 'demoted', 'removed'] as $ineligible) {
        $cases[$ineligible.' reverted'] = [$ineligible, ['tags' => ['mw-reverted']], []];
        $cases[$ineligible.' revision deleted'] = [$ineligible, ['exists' => false], []];
        $cases[$ineligible.' page deleted'] = [$ineligible, [], ['exists' => false]];
    }

    return $cases;
});
dataset('MaddraxikonStreakServiceTest.replacementIdentityEvidence', static fn (): array => [['valid'], ['invalid'], ['pending']]);
dataset('MaddraxikonStreakServiceTest.unchangedChecks', static fn (): array => ['valid' => [[], [], 'valid', null], 'invalid' => [['tags' => ['mw-reverted']], ['tags' => ['mw-reverted']], 'invalid', 'revision_reverted'], 'different rejection reason' => [['tags' => ['mw-reverted']], ['exists' => false], 'invalid', 'revision_missing']]);
dataset('MaddraxikonStreakServiceTest.invalidSources', static fn (): array => [[['bot' => true], [], [], 'source_hidden_or_bot'], [['anonymous' => true], [], [], 'source_hidden_or_bot'], [['user_hidden' => true], [], [], 'source_hidden_or_bot'], [['namespace_id' => 1], [], [], 'namespace_excluded'], [[], ['exists' => false], [], 'revision_missing'], [[], [], ['exists' => false], 'revision_missing'], [[], ['user_id' => 9999999], [], 'revision_identity_mismatch'], [[], ['page_id' => 9999999], [], 'revision_identity_mismatch'], [[], ['user_hidden' => true], [], 'revision_hidden'], [[], ['sha1_hidden' => true], [], 'revision_hidden'], [[], ['text_hidden' => true], [], 'revision_hidden'], [[], ['suppressed' => true], [], 'revision_hidden'], [[], ['tags' => ['mw-reverted']], [], 'revision_reverted'], [['type' => 'new', 'namespace_id' => 10], [], [], 'not_an_article'], [['type' => 'new'], [], ['redirect' => true], 'page_is_redirect']]);
dataset('MaddraxikonStreakServiceTest.clockChanges', static fn (): array => [['2026-03-28T23:55:00+01:00', '2026-03-29T03:01:00+02:00', '2026-03-30T00:01:00+02:00'], ['2026-10-24T23:55:00+02:00', '2026-10-25T02:30:00+01:00', '2026-10-26T00:01:00+01:00'], ['2026-12-31T23:55:00+01:00', '2027-01-01T00:01:00+01:00', '2027-01-02T00:01:00+01:00']]);
dataset('MaddraxikonStreakServiceTest.scopedRecheckOptions', static function (): array {
    $cases = [];
    foreach ([false, true] as $pretend) {
        $label = $pretend ? 'preview ' : 'write ';
        $cases[$label.'user'] = [['--user' => true], $pretend];
        $cases[$label.'from'] = [['--from' => '2026-10-01'], $pretend];
        $cases[$label.'user and from'] = [['--user' => true, '--from' => '2026-10-01'], $pretend];
    }

    return $cases;
});
dataset('MaddraxikonStreakServiceTest.localMidnights', static fn (): array => ['summer time' => ['2026-10-20T00:05:00+02:00'], 'after spring transition' => ['2026-03-30T00:05:00+02:00'], 'after autumn transition' => ['2026-10-26T00:05:00+01:00'], 'new year' => ['2027-01-01T00:05:00+01:00']]);
dataset('MaddraxikonStreakServiceTest.malformedChecks', static fn (): array => [[[]], [['user_id' => 7]]]);
beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-20T12:00:00+02:00'));
    config(['maddraxikon.streaks.enabled' => true, 'maddraxikon.streaks.starts_at' => '2026-10-01T00:00:00+02:00', 'maddraxikon.streaks.batch_size' => 100, 'maddraxikon.features.awards_enabled' => true, 'maddraxikon.features.sync_enabled' => true, 'maddraxikon.features.linking_enabled' => true]);
    $this->member = User::factory()->create();
    Team::membersTeam()->users()->attach($this->member, ['role' => Role::Mitglied->value]);
    $this->link = MaddraxikonAccountLink::factory()->create(['user_id' => $this->member->id, 'verified_at' => '2026-09-30T00:00:00Z', 'streak_verified_at_epoch' => CarbonImmutable::parse('2026-09-30T00:00:00Z')->timestamp]);
    MaddraxikonSyncState::factory()->create(['wiki_key' => config('maddraxikon.wiki_key'), 'initial_watermark_at' => '2026-09-29T00:00:00Z', 'watermark_at' => now(), 'last_succeeded_at' => now()]);
    $api = Mockery::mock(MaddraxikonApiClient::class);
    $api->shouldReceive('streakRevisionDetails')->andReturnUsing(fn (array $ids): array => MaddraxikonContribution::query()->whereIn('revision_id', $ids)->get()->mapWithKeys(fn ($source) => [$source->revision_id => ['exists' => true, 'page_id' => $source->page_id, 'user_id' => $source->wiki_user_id, 'tags' => [], ...$this->revisionOverrides[$source->revision_id] ?? []]])->all());
    $api->shouldReceive('streakPageDetails')->andReturnUsing(fn (array $ids) => collect($ids)->mapWithKeys(fn ($id) => [$id => ['exists' => true, 'namespace_id' => 0, 'redirect' => false, 'size' => 1, ...$this->pageOverrides[$id] ?? []]])->all());
    $this->app->instance(MaddraxikonApiClient::class, $api);
});
test('epoch backfill allows legacy discovery without bypassing rollout or verification delay', function (): void {
    config(['app.timezone' => 'Europe/Berlin']);
    $before = $this->legacySource('2026-09-30 23:59:59');
    $atLaunch = $this->legacySource('2026-10-01 00:00:00');
    $atDelay = $this->legacySource('2026-10-19 12:00:00');
    $tooEarly = $this->legacySource('2026-10-19 12:00:01');
    $this->backfillLegacyEpochs();
    expect($this->evaluate())->toBe(2)
        ->and($before->fresh()->streak_activity_date)->toBeNull()
        ->and($atLaunch->fresh()->streak_status)->toBe('valid')
        ->and($atDelay->fresh()->streak_status)->toBe('valid')
        ->and($tooEarly->fresh()->streak_status)->toBe('pending')
        ->and($this->points())->toBe(2);
    $this->travel(1)->seconds();
    expect($this->evaluate())->toBe(0)
        ->and($tooEarly->fresh()->streak_status)->toBe('valid')
        ->and($this->points())->toBe(2);
    $this->revisionOverrides[$atLaunch->revision_id] = ['tags' => ['mw-reverted']];
    $atLaunch->fresh()->forceFill(['streak_next_check_epoch' => 0])->save();
    expect($this->evaluate(true))->toBe(1)
        ->and($this->points())->toBe(1);
});
test('backfilled legacy sources block incomplete reconciliation and remain visible in summary', function (): void {
    config(['app.timezone' => 'Europe/Berlin']);
    $this->source('2026-10-17T12:00:00+02:00');
    $this->evaluate();
    $this->legacySource('2026-10-18 12:00:00');
    $last = $this->legacySource('2026-10-19 12:00:00');
    app(MaddraxikonStreakTracker::class)->record($last, $this->link);
    $last->forceFill(['streak_status' => 'valid'])->save();
    DB::table('maddraxikon_contributions')->where('id', $last->id)->update(['occurred_at' => '2026-10-19 12:00:00', 'occurred_at_epoch' => null]);
    $this->backfillLegacyEpochs();
    expect(app(MaddraxikonStreakService::class)->reconcile($this->member->id))->toBe(0)
        ->and($this->points())->toBe(1);
    $summary = app(MaddraxikonStreakSummary::class)->forUser($this->member);
    expect($summary['week'][5]['status'])->toBe('pending')
        ->and($summary['pending'])->toBeTrue()
        ->and($summary['next'])->toBeNull()
        ->and($this->evaluate())->toBe(2)
        ->and($this->points())->toBe(6)
        ->and(app(MaddraxikonStreakSummary::class)->forUser($this->member)['current'])->toBe(3);
});
test('five days restart and duplicate runs', function (): void {
    foreach ([10, 11, 12, 13, 14, 16] as $day) {
        $this->source("2026-10-{$day}T12:00:00+02:00");
    }
    expect($this->evaluate())->toBe(6)
        ->and(MaddraxikonStreakDay::query()->orderBy('activity_date')->pluck('settled_points')->all())->toBe([1, 2, 3, 4, 5, 1])
        ->and($this->points())->toBe(16)
        ->and($this->evaluate())->toBe(0)
        ->and($this->evaluate(true))->toBe(0);
    $this->assertDatabaseCount('maddraxikon_streak_bookings', 6);
});
test('multiple small edits and article pay only one daily bonus outside regular cap', function (): void {
    $this->source(attributes: ['minor' => true, 'old_size' => 600, 'new_size' => 590]);
    $this->source(attributes: ['type' => 'new', 'new_size' => 1]);
    app(BaxxBookingWriter::class)->record($this->member->id, Team::membersTeam()->id, 10);
    expect($this->evaluate())->toBe(1)
        ->and($this->points())->toBe(11);
    $this->assertDatabaseCount('maddraxikon_reward_events', 0);
    $this->assertDatabaseHas('maddraxikon_streak_days', ['valid_count' => 2, 'settled_points' => 1]);
});
test('thirty days have no bonus cap', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-02T12:00:00+01:00'));
    MaddraxikonSyncState::query()->first()->update(['watermark_at' => now(), 'last_succeeded_at' => now()]);
    for ($day = 1; $day <= 30; $day++) {
        $this->source(sprintf('2026-10-%02dT12:00:00+02:00', $day));
    }
    expect($this->evaluate())->toBe(30)
        ->and($this->points())->toBe(465);
});
test('reverted middle day recalculates followers and restoration is idempotent', function (): void {
    $sources = [];
    foreach ([10, 11, 12, 13, 14] as $day) {
        $sources[] = $this->source("2026-10-{$day}T12:00:00+02:00");
    }
    $this->evaluate();
    $this->revisionOverrides[$sources[2]->revision_id] = ['tags' => ['mw-reverted']];
    MaddraxikonContribution::query()->update(['streak_next_check_epoch' => 0]);
    expect($this->evaluate(true))->toBe(3)
        ->and($this->points())->toBe(6)
        ->and(MaddraxikonStreakDay::query()->orderBy('activity_date')->pluck('settled_points')->all())->toBe([1, 2, 0, 1, 2])
        ->and($this->evaluate(true))->toBe(0)
        ->and(app(MaddraxikonStreakSummary::class)->forUser($this->member)['record'])->toBe(2);
    $this->revisionOverrides = [];
    MaddraxikonContribution::query()->update(['streak_next_check_epoch' => 0]);
    expect($this->evaluate(true))->toBe(3)
        ->and($this->points())->toBe(15);
});
test('other valid source preserves day after deletion', function (): void {
    $source = $this->source();
    $this->source();
    $this->evaluate();
    $this->pageOverrides[$source->page_id] = ['exists' => false];
    MaddraxikonContribution::query()->update(['streak_next_check_epoch' => 0]);
    expect($this->evaluate(true))->toBe(0)
        ->and($this->points())->toBe(1);
});
test('late import bridges series and pays only differences', function (): void {
    $this->source();
    $this->source('2026-10-12T12:00:00+02:00');
    $this->evaluate();
    expect($this->points())->toBe(2);
    $this->source('2026-10-11T12:00:00+02:00');
    $this->evaluate();
    expect($this->points())->toBe(6);
});
test('twenty four hours are independent of session deadline', function (): void {
    $source = $this->source('2026-10-19T12:01:00+02:00', ['eligible_after' => now()->subDay()]);
    expect($this->evaluate())->toBe(0)
        ->and($source->fresh()->streak_status)->toBe('pending');
    $this->travel(2)->minutes();
    $source->update(['eligible_after' => now()->addDays(2)]);
    expect($this->evaluate())->toBe(1);
});
test('midnight edits in one session count as two days', function (): void {
    $this->source('2026-10-10T23:59:00+02:00', ['session_anchor_revision_id' => 123]);
    $this->source('2026-10-11T00:01:00+02:00', ['session_anchor_revision_id' => 123]);
    $this->evaluate();
    expect($this->points())->toBe(3);
});
test('pending predecessor prevents premature smaller bonus', function (): void {
    $this->source(attributes: ['status_reason' => 'recovery_bot_status_unverifiable']);
    $this->source('2026-10-11T12:00:00+02:00');
    expect($this->evaluate())->toBe(0)
        ->and($this->points())->toBe(0);
    $this->assertDatabaseHas('maddraxikon_streak_days', ['activity_date' => '2026-10-11', 'streak_length' => null]);
});
test('recovery unknown bot can be resolved without touching regular rewards', function (): void {
    $source = $this->source(attributes: ['status' => 'rejected', 'status_reason' => 'recovery_bot_status_unverifiable']);
    $this->evaluate();
    expect($source->fresh()->streak_status)->toBe('pending');
    $source->fresh()->forceFill(['streak_bot_verified' => true, 'streak_next_check_epoch' => 0])->save();
    expect($this->evaluate(true))->toBe(1)
        ->and($source->fresh()->status->value)->toBe('rejected');
});
test('disabled feature never pays', function (string $key, mixed $value): void {
    $this->source();
    config([$key => $value]);
    expect($this->evaluate())->toBe(0)
        ->and($this->points())->toBe(0);
})->with('MaddraxikonStreakServiceTest.disabledConfigurations');
test('prelaunch activity does not count', function (): void {
    $this->source('2026-09-30T12:00:00+02:00');
    $this->source('2026-10-01T00:00:00+02:00');
    expect($this->evaluate())->toBe(1)
        ->and($this->points())->toBe(1);
});
test('recovery or missing initial coverage prevents invented gaps', function (): void {
    $this->source();
    $sync = MaddraxikonSyncState::query()->first();
    $sync->update(['recovery_required_at' => now()]);
    expect($this->evaluate())->toBe(0);
    $sync->update(['recovery_required_at' => null, 'initial_watermark_at' => now()->subDay()]);
    expect($this->evaluate())->toBe(0);
});
test('api failure keeps confirmed points and can retry', function (): void {
    $this->source();
    $this->evaluate();
    MaddraxikonContribution::query()->update(['streak_next_check_epoch' => 0]);
    $api = Mockery::mock(MaddraxikonApiClient::class);
    $api->shouldReceive('streakRevisionDetails')->andThrow(new RuntimeException('temporary failure'));
    $this->app->instance(MaddraxikonApiClient::class, $api);
    try {
        $this->evaluate(true);
        $this->fail('Expected failure');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('temporary failure');
    }
    expect($this->points())->toBe(1);
    $this->assertDatabaseHas('maddraxikon_contributions', ['streak_status' => 'valid']);
});
test('bounded batches resume and unknown days do not starve later days', function (): void {
    config(['maddraxikon.streaks.batch_size' => 1]);
    $this->source(attributes: ['status_reason' => 'recovery_bot_status_unverifiable']);
    $this->source('2026-10-12T12:00:00+02:00');
    for ($i = 0; $i < 5; $i++) {
        $this->evaluate();
    }
    expect($this->points())->toBe(1);
});
test('reconciliation uses constant contribution queries per batch', function (int $batchSize): void {
    config(['maddraxikon.streaks.batch_size' => $batchSize]);
    $start = CarbonImmutable::parse('2026-10-01T12:00:00+02:00');
    $this->travelTo($start->addDays($batchSize + 2));
    MaddraxikonSyncState::query()->sole()->update(['watermark_at' => now(), 'last_succeeded_at' => now()]);
    for ($i = 0; $i <= $batchSize; $i++) {
        $this->source($start->addDays($i)->toIso8601String())->forceFill(['streak_status' => 'valid'])->save();
    }
    $extra = $this->source($start->addHour()->toIso8601String());
    $service = app(MaddraxikonStreakService::class);
    DB::flushQueryLog();
    DB::enableQueryLog();
    try {
        $bookings = $service->reconcile($this->member->id);
        $sourceQueries = collect(DB::getQueryLog())->filter(fn (array $query) => str_contains($query['query'], 'maddraxikon_contributions'));
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
    // One completeness check and one source fetch, independent of the number of days.
    expect($sourceQueries)->toHaveCount(2);
    expect($bookings)->toBe($batchSize)
        ->and($this->points())->toBe((int) ($batchSize * ($batchSize + 1) / 2));
    $first = MaddraxikonStreakDay::query()->orderBy('activity_date')->firstOrFail();
    expect($first->valid_count)->toBe(1)
        ->and($first->pending_count)->toBe(1)
        ->and($first->bookings()->sole()->source_revision_ids)->toContain($extra->revision_id);
    $next = $start->addDays($batchSize)->toDateString();
    expect(MaddraxikonStreakState::query()->sole()->dirty_from)->toBe($next);
    $this->assertDatabaseHas('maddraxikon_streak_days', ['activity_date' => $next, 'settled_points' => 0, 'calculation_version' => 0]);
    expect($service->reconcile($this->member->id))->toBe(1)
        ->and($this->points())->toBe((int) (($batchSize + 1) * ($batchSize + 2) / 2))
        ->and(MaddraxikonStreakState::query()->sole()->dirty_from)->toBeNull();
})->with('MaddraxikonStreakServiceTest.reconciliationBatchSizes');
test('grouped sources stay scoped to the member wiki and day', function (): void {
    $first = $this->source()->forceFill(['streak_status' => 'valid']);
    $first->save();
    $last = $this->source('2026-10-12T12:00:00+02:00')->forceFill(['streak_status' => 'valid']);
    $last->save();
    $emptyDay = MaddraxikonStreakDay::query()->create(['wiki_key' => $first->wiki_key, 'user_id' => $this->member->id, 'activity_date' => '2026-10-11', 'starts_at_epoch' => app(MaddraxikonStreakSettings::class)->start()->timestamp]);
    foreach ([['wiki_key' => 'another-wiki'], ['user_id' => User::factory()->create()->id]] as $attributes) {
        MaddraxikonContribution::factory()->create(['wiki_key' => $first->wiki_key, 'user_id' => $this->member->id, 'wiki_user_id' => $this->link->wiki_user_id, 'account_link_id' => null, 'occurred_at' => '2026-10-11T12:00:00+02:00', ...$attributes])->forceFill(['streak_activity_date' => '2026-10-11', 'streak_status' => 'valid', 'streak_verified_at_epoch' => $this->link->streak_verified_at_epoch])->save();
    }
    $service = app(MaddraxikonStreakService::class);
    expect($service->reconcile($this->member->id, pretend: true))->toBe(2)
        ->and($this->points())->toBe(0)
        ->and($service->reconcile($this->member->id))->toBe(2)
        ->and($this->points())->toBe(2)
        ->and(MaddraxikonStreakDay::query()->orderBy('activity_date')->pluck('settled_points')->all())->toBe([1, 0, 1])
        ->and($emptyDay->fresh()->status)->toBe('inactive')
        ->and($emptyDay->fresh()->valid_count)->toBe(0)
        ->and(MaddraxikonStreakDay::query()->where('activity_date', '2026-10-10')->sole()->bookings()->sole()->source_revision_ids)->toBe([$first->revision_id])
        ->and(MaddraxikonStreakDay::query()->where('activity_date', '2026-10-12')->sole()->bookings()->sole()->source_revision_ids)->toBe([$last->revision_id]);
});
test('reconciliation with no days clears the dirty cursor without awards', function (): void {
    app(MaddraxikonStreakSettings::class)->activate();
    $state = MaddraxikonStreakState::query()->create(['wiki_key' => config('maddraxikon.wiki_key'), 'user_id' => $this->member->id, 'dirty_from' => '2026-10-10']);
    expect(app(MaddraxikonStreakService::class)->reconcile($this->member->id))->toBe(0)
        ->and($state->fresh()->dirty_from)->toBeNull()
        ->and($this->points())->toBe(0);
    $this->assertDatabaseCount('maddraxikon_streak_bookings', 0);
});
test('read only preview makes no changes', function (): void {
    $source = $this->source();
    $source->forceFill(['streak_status' => 'valid'])->save();
    expect(app(MaddraxikonStreakService::class)->reconcile($this->member->id, pretend: true))->toBe(1)
        ->and($this->points())->toBe(0);
    $this->assertDatabaseHas('maddraxikon_streak_days', ['settled_points' => 0]);
});
test('start cannot be changed after tracking', function (): void {
    $this->source();
    config(['maddraxikon.streaks.starts_at' => '2026-10-02T00:00:00+02:00']);
    expect(app(MaddraxikonStreakSettings::class)->pinStart()->starts_at_epoch)->toBe(CarbonImmutable::parse('2026-10-01T00:00:00+02:00')->timestamp)
        ->and($this->evaluate())->toBe(1)
        ->and($this->points())->toBe(1);
});
test('disconnected and ineligible members receive no new bonus', function (): void {
    $this->source();
    $this->link->update(['status' => 'disconnected', 'disconnected_at' => now()]);
    expect($this->evaluate())->toBe(0);
    $this->link->refresh()->update(['status' => 'active', 'disconnected_at' => null]);
    Team::membersTeam()->users()->updateExistingPivot($this->member->id, ['role' => Role::Anwaerter->value]);
    expect($this->evaluate())->toBe(0);
});
test('historical rechecks correct paid days after eligibility ends', function (string $ineligible, array $revision, array $page): void {
    Mail::fake();
    $this->source();
    $middle = $this->source('2026-10-11T12:00:00+02:00');
    $this->source('2026-10-12T12:00:00+02:00');
    expect($this->evaluate())->toBe(3)
        ->and($this->points())->toBe(6);
    match ($ineligible) {
        'disconnected' => app(AccountLinkService::class)->disconnect($this->member),
        'demoted' => Team::membersTeam()->users()->updateExistingPivot($this->member->id, ['role' => Role::Anwaerter->value]),
        'removed' => Team::membersTeam()->users()->detach($this->member),
    };
    $this->revisionOverrides[$middle->revision_id] = $revision;
    $this->pageOverrides[$middle->page_id] = $page;
    MaddraxikonContribution::query()->update(['streak_next_check_epoch' => 0]);
    expect($this->evaluate(true))->toBe(2)
        ->and($middle->fresh()->streak_status)->toBe('invalid')
        ->and(MaddraxikonStreakDay::query()->orderBy('activity_date')->pluck('settled_points')->all())->toBe([1, 0, 1])
        ->and($this->points())->toBe(2)
        ->and(MaddraxikonStreakBooking::query()->where('points_delta', '<', 0)->orderBy('id')->pluck('points_delta')->all())->toBe([-2, -2])
        ->and($this->evaluate(true))->toBe(0);
    // Historical validity can recover, but positive corrections still require eligibility.
    $this->revisionOverrides = $this->pageOverrides = [];
    MaddraxikonContribution::query()->update(['streak_next_check_epoch' => 0]);
    expect($this->evaluate(true))->toBe(0)
        ->and($middle->fresh()->streak_status)->toBe('valid')
        ->and($this->points())->toBe(2)
        ->and(MaddraxikonStreakState::query()->sole()->dirty_from)->toBe('2026-10-11');
    $this->link->refresh()->update(['status' => 'active', 'disconnected_at' => null]);
    Team::membersTeam()->users()->syncWithoutDetaching([$this->member->id => ['role' => Role::Mitglied->value]]);
    expect($this->evaluate())->toBe(2)
        ->and($this->points())->toBe(6);
})->with('MaddraxikonStreakServiceTest.historicalCorrections');
test('positive corrections require current identity evidence for each day', function (string $newStatus): void {
    Mail::fake();
    config(['maddraxikon.evaluation_delay_hours' => 1]);
    $this->travelTo(CarbonImmutable::parse('2026-10-11T10:00:00+02:00'));
    $first = $this->source('2026-10-10T08:00:00+02:00');
    $second = $this->source('2026-10-11T08:00:00+02:00');
    $sync = MaddraxikonSyncState::query()->sole();
    $sync->update(['watermark_at' => now(), 'last_succeeded_at' => now()]);
    expect($this->evaluate())->toBe(2)
        ->and($this->points())->toBe(3);
    $this->travelTo(CarbonImmutable::parse('2026-10-11T11:00:00+02:00'));
    Team::membersTeam()->users()->updateExistingPivot($this->member->id, ['role' => Role::Admin->value]);
    $links = app(AccountLinkService::class);
    $oldLink = $this->link;
    $links->disconnect($this->member);
    $links->releaseDisconnectedLink($this->member, $oldLink->fresh(), 'Verknüpfung für eine andere Wiki-Identität freigeben.');
    $this->link = $links->activate($this->member, new MaddraxikonIdentity('replacement-'.$oldLink->id, $oldLink->wiki_user_id + 1, 'Neues Wiki-Konto'), 'test', now());
    expect($first->fresh()->account_link_id)->toBeNull()
        ->and($second->fresh()->account_link_id)->toBeNull();
    $this->travelTo(CarbonImmutable::parse('2026-10-11T13:00:00+02:00'));
    $current = $this->source($newStatus === 'pending' ? '2026-10-11T12:30:00+02:00' : '2026-10-11T12:00:00+02:00');
    if ($newStatus === 'invalid') {
        $this->revisionOverrides[$current->revision_id] = ['tags' => ['mw-reverted']];
    }
    $sync->update(['watermark_at' => now(), 'last_succeeded_at' => now()]);
    expect($this->evaluate())->toBe(0)
        ->and($current->fresh()->streak_status)->toBe($newStatus);
    $this->revisionOverrides[$first->revision_id] = ['tags' => ['mw-reverted']];
    MaddraxikonContribution::query()->update(['streak_next_check_epoch' => 0]);
    expect($this->evaluate(true))->toBe(2)
        ->and($this->points())->toBe(1)
        ->and(MaddraxikonStreakBooking::query()->where('points_delta', '<', 0)->orderBy('id')->pluck('points_delta')->all())->toBe([-1, -1]);
    unset($this->revisionOverrides[$first->revision_id]);
    MaddraxikonContribution::query()->update(['streak_next_check_epoch' => 0]);
    expect($this->evaluate(true))->toBe($newStatus === 'valid' ? 1 : 0)
        ->and($first->fresh()->streak_status)->toBe('valid')
        ->and(MaddraxikonStreakDay::query()->orderBy('activity_date')->pluck('settled_points')->all())->toBe([0, $newStatus === 'valid' ? 2 : 1])
        ->and($this->points())->toBe($newStatus === 'valid' ? 2 : 1)
        ->and($this->evaluate(true))->toBe(0);
})->with('MaddraxikonStreakServiceTest.replacementIdentityEvidence');
test('rollback and redeployment preserve the complete streak journal and source snapshots', function (): void {
    $first = $this->source();
    $this->source('2026-10-11T12:00:00+02:00');
    $this->evaluate();
    $this->revisionOverrides[$first->revision_id] = ['tags' => ['mw-reverted']];
    MaddraxikonContribution::query()->update(['streak_next_check_epoch' => 0]);
    $this->evaluate(true);
    expect($this->points())->toBe(1);
    $this->assertDatabaseCount('maddraxikon_streak_bookings', 4);
    $this->link->update(['verified_at' => now()]);
    $tables = ['maddraxikon_streak_bookings', 'maddraxikon_streak_days', 'maddraxikon_streak_states', 'maddraxikon_streak_runs', 'maddraxikon_contributions', 'maddraxikon_account_links', 'user_points'];
    $before = [];
    foreach ($tables as $table) {
        $before[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
    }
    $migrations = ['2026_10_24_140000_backfill_maddraxikon_contribution_epochs', '2026_10_24_150000_create_maddraxikon_streaks', '2026_10_24_160000_activate_maddraxikon_streaks'];
    DB::table('migrations')->whereIn('migration', $migrations)->update(['batch' => DB::table('migrations')->max('batch') + 1]);
    $this->artisan('migrate:rollback', ['--force' => true])->assertSuccessful();
    foreach ($tables as $table) {
        expect(DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all())->toBe($before[$table]);
    }
    expect(DB::table('migrations')->whereIn('migration', $migrations)->count())->toBe(0);
    $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    foreach ($tables as $table) {
        expect(DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all())->toBe($before[$table]);
    }
    expect(DB::table('migrations')->whereIn('migration', $migrations)->count())->toBe(3)
        ->and($this->evaluate())->toBe(0)
        ->and($this->points())->toBe(1);
    $this->assertDatabaseCount('maddraxikon_streak_bookings', 4);
});
test('unchanged rechecks refresh evidence without rewriting settled days', function (array $before, array $after, string $status, ?string $reason): void {
    $source = $this->source();
    $this->revisionOverrides[$source->revision_id] = $before;
    $this->evaluate();
    $day = MaddraxikonStreakDay::query()->sole()->getAttributes();
    $state = MaddraxikonStreakState::query()->sole()->getAttributes();
    $version = $source->fresh()->streak_check_version;
    $this->travel(1)->hours();
    $this->revisionOverrides[$source->revision_id] = $after;
    $source->fresh()->forceFill(['streak_next_check_epoch' => 0])->save();
    expect($this->evaluate(true))->toBe(0)
        ->and(MaddraxikonStreakDay::query()->sole()->getAttributes())->toBe($day)
        ->and(MaddraxikonStreakState::query()->sole()->getAttributes())->toBe($state)
        ->and($source->fresh()->streak_status)->toBe($status)
        ->and($source->fresh()->streak_reason)->toBe($reason)
        ->and($source->fresh()->streak_check_version)->toBe($version + 1)
        ->and($source->fresh()->streak_checked_at_epoch)->toBe(now()->timestamp)
        ->and($source->fresh()->streak_next_check_epoch)->toBeGreaterThan(now()->timestamp);
})->with('MaddraxikonStreakServiceTest.unchangedChecks');
test('unchanged historical check does not rewind an ongoing correction', function (): void {
    $sources = [];
    foreach ([10, 11, 12, 13, 14, 15] as $day) {
        $sources[] = $this->source("2026-10-{$day}T12:00:00+02:00");
    }
    $this->evaluate();
    config(['maddraxikon.streaks.batch_size' => 2]);
    $this->revisionOverrides[$sources[2]->revision_id] = ['tags' => ['mw-reverted']];
    $sources[2]->fresh()->forceFill(['streak_next_check_epoch' => 0])->save();
    expect($this->evaluate(true))->toBe(2)
        ->and(MaddraxikonStreakState::query()->sole()->dirty_from)->toBe('2026-10-14');
    $sources[0]->fresh()->forceFill(['streak_next_check_epoch' => 0])->save();
    expect($this->evaluate(true))->toBe(2)
        ->and($this->points())->toBe(9)
        ->and(MaddraxikonStreakState::query()->sole()->dirty_from)->toBeNull();
    $this->assertDatabaseCount('maddraxikon_streak_bookings', 10);
});
test('unchanged pending evidence preserves forward progress and pending cursor', function (): void {
    $pending = $this->source(attributes: ['status_reason' => 'recovery_bot_status_unverifiable']);
    $this->source('2026-10-11T12:00:00+02:00');
    $this->source('2026-10-13T12:00:00+02:00');
    expect($this->evaluate())->toBe(1);
    config(['maddraxikon.streaks.batch_size' => 1]);
    $this->evaluate();
    expect(MaddraxikonStreakState::query()->sole()->dirty_from)->toBe('2026-10-11');
    $pending->fresh()->forceFill(['streak_next_check_epoch' => 0])->save();
    expect($this->evaluate(true))->toBe(0)
        ->and(MaddraxikonStreakState::query()->sole()->dirty_from)->toBe('2026-10-13')
        ->and(MaddraxikonStreakState::query()->sole()->pending_from)->toBe('2026-10-10')
        ->and($this->evaluate())->toBe(0)
        ->and(MaddraxikonStreakState::query()->sole()->dirty_from)->toBe('2026-10-10')
        ->and(MaddraxikonStreakState::query()->sole()->pending_from)->toBeNull()
        ->and($this->points())->toBe(1);
});
test('transaction rolls back when booking fails', function (): void {
    $source = $this->source();
    $source->forceFill(['streak_status' => 'valid'])->save();
    MaddraxikonStreakBooking::creating(fn () => throw new RuntimeException('journal failure'));
    try {
        app(MaddraxikonStreakService::class)->reconcile($this->member->id);
        $this->fail('Expected failure');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('journal failure');
    } finally {
        MaddraxikonStreakBooking::flushEventListeners();
    }
    expect($this->points())->toBe(0);
    $this->assertDatabaseHas('maddraxikon_streak_days', ['settled_points' => 0]);
    expect(MaddraxikonStreakState::query()->first()->dirty_from)->not->toBeNull();
});
test('invalid sources never secure a day', function (array $attributes, array $revision, array $page, string $reason): void {
    $source = $this->source(attributes: $attributes);
    $this->revisionOverrides[$source->revision_id] = $revision;
    $this->pageOverrides[$source->page_id] = $page;
    $this->evaluate();
    expect($this->points())->toBe(0)
        ->and($source->fresh()->streak_reason)->toBe($reason);
})->with('MaddraxikonStreakServiceTest.invalidSources');
test('incomplete identity keeps existing bonus', function (): void {
    $source = $this->source();
    $this->evaluate();
    MaddraxikonContribution::query()->update(['streak_next_check_epoch' => 0]);
    $this->revisionOverrides[$source->revision_id] = ['user_id' => null];
    try {
        $this->evaluate(true);
        $this->fail('Incomplete response must stay pending');
    } catch (MaddraxikonApiException) {
        expect($this->points())->toBe(1);
    }
});
test('local days across clock and year changes', function (string $first, string $second, string $third): void {
    config(['maddraxikon.streaks.starts_at' => '2026-01-01T00:00:00+01:00']);
    $this->link->update(['verified_at' => '2025-12-01T00:00:00Z', 'streak_verified_at_epoch' => CarbonImmutable::parse('2025-12-01')->timestamp]);
    $this->travelTo(CarbonImmutable::parse($third)->addDays(3));
    MaddraxikonSyncState::query()->first()->update(['initial_watermark_at' => '2025-12-01', 'watermark_at' => now(), 'last_succeeded_at' => now()]);
    $this->source($first);
    $this->source($second);
    $this->source($third);
    $this->evaluate();
    expect($this->points())->toBe(6);
})->with('MaddraxikonStreakServiceTest.clockChanges');
test('repeated autumn hour counts only once', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-28T12:00:00+01:00'));
    MaddraxikonSyncState::query()->first()->update(['watermark_at' => now(), 'last_succeeded_at' => now()]);
    $this->source('2026-10-25T02:30:00+02:00');
    $this->source('2026-10-25T02:30:00+01:00');
    expect($this->evaluate())->toBe(1)
        ->and($this->points())->toBe(1);
});
test('spent points and later inactivity do not prevent owed reversal', function (): void {
    $source = $this->source();
    $this->evaluate();
    app(BaxxBookingWriter::class)->record($this->member->id, Team::membersTeam()->id, -1);
    $source->fresh()->forceFill(['streak_status' => 'invalid', 'streak_reason' => 'revision_reverted'])->save();
    app(MaddraxikonStreakTracker::class)->dirty($source->wiki_key, $this->member->id, $source->streak_activity_date);
    Team::membersTeam()->users()->updateExistingPivot($this->member->id, ['role' => Role::Anwaerter->value]);
    expect(app(MaddraxikonStreakService::class)->reconcile($this->member->id))->toBe(1)
        ->and($this->points())->toBe(-1);
});
test('only an admin can attest recovery evidence', function (): void {
    $source = $this->source(attributes: ['status_reason' => 'recovery_bot_status_unverifiable']);
    try {
        app(MaddraxikonStreakService::class)->verifyRecoveredSource($source->id, $this->member, 'Manueller Revisionsnachweis');
        $this->fail('Member must not attest evidence');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(403);
    }
    Team::membersTeam()->users()->updateExistingPivot($this->member->id, ['role' => Role::Admin->value]);
    app(MaddraxikonStreakService::class)->verifyRecoveredSource($source->id, $this->member, 'Manueller Revisionsnachweis');
    expect($source->fresh()->streak_bot_verified)->toBeTrue()
        ->and($source->fresh()->streak_reviewed_by)->toBe($this->member->id);
});
test('preview command and invalid date arguments', function (): void {
    $source = $this->source();
    $source->forceFill(['streak_status' => 'valid'])->save();
    $this->artisan('maddraxikon:streaks', ['--user' => $this->member->id, '--from' => '2026-10-10', '--pretend' => true])->expectsOutputToContain('Vorschau: 1')->assertSuccessful();
    expect($this->points())->toBe(0);
    $this->artisan('maddraxikon:streaks', ['--from' => 'not-a-date'])->assertFailed();
});
test('reconsent preserves import boundary but reconnection excludes the gap', function (): void {
    Mail::fake();
    $service = app(AccountLinkService::class);
    $identity = new MaddraxikonIdentity($this->link->oauth_subject, $this->link->wiki_user_id, $this->link->wiki_username);
    $original = $this->link->streak_verified_at_epoch;
    $this->link = $service->activate($this->member, $identity, 'test', now());
    expect($this->link->streak_verified_at_epoch)->toBe($original)
        ->and($this->link->verified_at_epoch)->toBe(now()->timestamp);
    $sync = MaddraxikonSyncState::query()->first();
    $sync->update(['watermark_at' => now()->subHours(2)]);
    $at = CarbonImmutable::now()->subHour();
    $change = ['type' => 'edit', 'ns' => 0, 'title' => 'Kleine Korrektur', 'pageid' => 42, 'revid' => 987601, 'old_revid' => 987600, 'rcid' => 987601, 'user' => $this->link->wiki_username, 'userid' => $this->link->wiki_user_id, 'oldlen' => 100, 'newlen' => 99, 'timestamp' => $at->utc()->format('Y-m-d\TH:i:s\Z'), 'minor' => true, 'bot' => false, 'anon' => false, 'tags' => []];
    app(MaddraxikonApiClient::class)->shouldReceive('recentChanges')->once()->andReturn([$change]);
    expect(app(MaddraxikonContributionImporter::class)->sync())->toBe(1);
    $source = MaddraxikonContribution::query()->sole();
    expect($source->streak_verified_at_epoch)->toBe($original)
        ->and($source->streak_status)->toBe('pending');
    $this->travelTo($at->addDay());
    $sync->update(['watermark_at' => now(), 'last_succeeded_at' => now()]);
    expect($this->evaluate())->toBe(1);
    $service->disconnect($this->member);
    $gap = CarbonImmutable::now()->addHour();
    $this->travelTo($gap->addHour());
    $this->link = $service->activate($this->member, $identity, 'test', now());
    expect($this->link->streak_verified_at_epoch)->toBe(now()->timestamp)
        ->and($source->fresh()->streak_verified_at_epoch)->toBe($original)
        ->and($this->source($gap->toIso8601String())->streak_status)->toBe('excluded')
        ->and($this->source(now()->toIso8601String())->streak_status)->toBe('pending')
        ->and($this->points())->toBe(1);
});
test('exact launch and verification boundaries and maintenance import', function (): void {
    config(['maddraxikon.streaks.enabled' => false]);
    $start = app(MaddraxikonStreakSettings::class)->start();
    $before = $this->source($start->subSecond()->toIso8601String());
    $at = $this->source($start->toIso8601String());
    expect($before->streak_activity_date)->toBeNull()
        ->and($at->streak_status)->toBe('pending')
        ->and($this->evaluate())->toBe(0);
    config(['maddraxikon.streaks.enabled' => true]);
    MaddraxikonRewardPolicy::query()->update(['new_articles_enabled' => false, 'edit_sessions_enabled' => false]);
    expect($this->evaluate())->toBe(1)
        ->and($this->points())->toBe(1);
});
test('api response without explicit missing markers preserves existing points', function (): void {
    $source = $this->source(attributes: ['type' => 'new']);
    $this->evaluate();
    $source->fresh()->forceFill(['streak_next_check_epoch' => 0])->save();
    $this->pageOverrides[$source->page_id] = ['namespace_id' => null];
    $this->expectException(MaddraxikonApiException::class);
    try {
        $this->evaluate(true);
    } finally {
        expect($this->points())->toBe(1);
    }
});
test('jobs and command process and recheck real bonuses', function (): void {
    $source = $this->source();
    $initial = new EvaluateMaddraxikonStreaks;
    $recheck = new EvaluateMaddraxikonStreaks(true);
    expect($recheck->uniqueId())->not->toBe($initial->uniqueId());
    $initial->handle(app(MaddraxikonStreakService::class));
    expect($this->points())->toBe(1);
    $this->revisionOverrides[$source->revision_id] = ['tags' => ['mw-reverted']];
    $source->fresh()->forceFill(['streak_next_check_epoch' => 0])->save();
    $this->artisan('maddraxikon:streaks', ['--recheck' => true])->expectsOutputToContain('1 Bonusdifferenzen')->assertSuccessful();
    expect($this->points())->toBe(0);
    $this->artisan('maddraxikon:streaks', ['--user' => 999999999])->assertFailed();
});
test('recently successful but backlogged import does not reset displayed series', function (): void {
    $this->source('2026-10-17T12:00:00+02:00');
    $this->evaluate();
    MaddraxikonSyncState::query()->first()->update(['watermark_at' => '2026-10-18T12:00:00+02:00', 'last_succeeded_at' => now()]);
    $summary = app(MaddraxikonStreakSummary::class)->forUser($this->member);
    expect($summary['delayed'])->toBeTrue()
        ->and($summary['current'])->toBe(1)
        ->and($summary['next'])->toBeNull()
        ->and($summary['week'][5]['status'])->toBe('unknown');
});
test('scoped recheck is explicitly rejected except for local preview', function (array $scope, bool $pretend): void {
    $source = $this->source();
    $this->evaluate();
    $source->fresh()->forceFill(['streak_next_check_epoch' => 0])->save();
    $this->revisionOverrides[$source->revision_id] = ['tags' => ['mw-reverted']];
    $stored = $source->fresh()->getAttributes();
    $api = Mockery::mock(MaddraxikonApiClient::class);
    $api->shouldNotReceive('streakRevisionDetails');
    $api->shouldNotReceive('streakPageDetails');
    $this->app->instance(MaddraxikonApiClient::class, $api);
    if (isset($scope['--user'])) {
        $scope['--user'] = $this->member->id;
    }
    $command = $this->artisan('maddraxikon:streaks', ['--recheck' => true, '--pretend' => $pretend, ...$scope]);
    if ($pretend) {
        $command->expectsOutputToContain('Vorschau: 0')->assertSuccessful();
    } else {
        $command->expectsOutputToContain('--recheck kann nicht mit --user oder --from kombiniert werden.')->assertFailed();
    }
    expect($source->fresh()->getAttributes())->toBe($stored)
        ->and($this->points())->toBe(1);
    $this->assertDatabaseCount('maddraxikon_streak_bookings', 1);
})->with('MaddraxikonStreakServiceTest.scopedRecheckOptions');
test('recent watermark before local midnight preserves last confirmed series', function (string $now): void {
    $this->travelTo(CarbonImmutable::parse($now));
    config(['maddraxikon.streaks.starts_at' => '2026-01-01T00:00:00+01:00']);
    $this->link->update(['streak_verified_at_epoch' => CarbonImmutable::parse('2025-12-01')->timestamp]);
    $today = CarbonImmutable::now('Europe/Berlin')->startOfDay();
    $sync = MaddraxikonSyncState::query()->sole();
    $sync->update(['initial_watermark_at' => '2025-12-01', 'watermark_at' => $today->subMinutes(5), 'last_succeeded_at' => now()]);
    $this->source($today->subDays(3)->setHour(12)->toIso8601String());
    $this->source($today->subDays(2)->setHour(12)->toIso8601String());
    $this->evaluate();
    $summary = app(MaddraxikonStreakSummary::class)->forUser($this->member);
    expect($summary['delayed'])->toBeTrue()
        ->and($summary['current'])->toBe(2)
        ->and($summary['next'])->toBeNull()
        ->and($summary['week'][5]['status'])->toBe('unknown');
    // Exactly midnight covers all of yesterday: a genuinely empty day may reset the series.
    $sync->update(['watermark_at' => $today]);
    $summary = app(MaddraxikonStreakSummary::class)->forUser($this->member);
    expect($summary['delayed'])->toBeFalse()
        ->and($summary['current'])->toBe(0)
        ->and($summary['next'])->toBe(1)
        ->and($summary['week'][5]['status'])->toBe('empty');
    // A late import instead keeps the confirmed series while its revision is checked.
    $this->source($today->subMinute()->toIso8601String());
    $summary = app(MaddraxikonStreakSummary::class)->forUser($this->member);
    expect($summary['current'])->toBe(2)
        ->and($summary['pending'])->toBeTrue()
        ->and($summary['week'][5]['status'])->toBe('pending')
        ->and($summary['next'])->toBeNull();
})->with('MaddraxikonStreakServiceTest.localMidnights');
test('valid source waiting for its reconciliation batch is not a known gap', function (): void {
    $this->source('2026-10-17T12:00:00+02:00');
    $this->source('2026-10-18T12:00:00+02:00');
    $this->evaluate();
    $source = $this->source('2026-10-19T09:00:00+02:00');
    MaddraxikonStreakState::query()->create(['wiki_key' => $source->wiki_key, 'user_id' => User::factory()->create()->id, 'dirty_from' => '2026-10-01', 'updated_at' => now()->subHour()]);
    config(['maddraxikon.streaks.batch_size' => 1]);
    expect($this->evaluate())->toBe(0)
        ->and($source->fresh()->streak_status)->toBe('valid');
    $this->assertDatabaseHas('maddraxikon_streak_days', ['user_id' => $this->member->id, 'activity_date' => '2026-10-19', 'status' => 'pending', 'streak_length' => null]);
    $summary = app(MaddraxikonStreakSummary::class)->forUser($this->member);
    expect($summary['delayed'])->toBeFalse()
        ->and($summary['recalculating'])->toBeFalse()
        ->and($summary['pending'])->toBeTrue()
        ->and($summary['week'][5]['status'])->toBe('pending')
        ->and($summary['current'])->toBe(2)
        ->and($summary['next'])->toBeNull()
        ->and($this->evaluate())->toBe(1);
    $summary = app(MaddraxikonStreakSummary::class)->forUser($this->member);
    expect($summary['pending'])->toBeFalse()
        ->and($summary['week'][5]['status'])->toBe('confirmed')
        ->and($summary['current'])->toBe(3)
        ->and($summary['next'])->toBe(4)
        ->and($this->points())->toBe(6);
});
test('confirmed gap resets display even when another old day is pending', function (): void {
    $this->source('2026-10-17T12:00:00+02:00');
    $this->evaluate();
    $this->source('2026-10-18T12:00:00+02:00');
    $summary = app(MaddraxikonStreakSummary::class)->forUser($this->member);
    expect($summary['current'])->toBe(0)
        ->and($summary['pending'])->toBeTrue();
});
test('long correction resumes after failure without losing or duplicating points', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-12-02T12:00:00+01:00'));
    MaddraxikonSyncState::query()->first()->update(['watermark_at' => now(), 'last_succeeded_at' => now()]);
    config(['maddraxikon.streaks.batch_size' => 10]);
    $start = CarbonImmutable::parse('2026-10-01T12:00:00+02:00');
    $sources = [];
    for ($i = 0; $i < 60; $i++) {
        $sources[] = $this->source($start->addDays($i)->toIso8601String());
    }
    for ($i = 0; $i < 6; $i++) {
        $this->evaluate();
    }
    expect($this->points())->toBe(1830);
    $source = $sources[19]->fresh();
    $source->forceFill(['streak_next_check_epoch' => 0])->save();
    $this->revisionOverrides[$source->revision_id] = ['tags' => ['mw-reverted']];
    expect($this->evaluate(true))->toBe(10);
    $settled = $this->points();
    $cursor = MaddraxikonStreakState::query()->sole()->dirty_from;
    MaddraxikonStreakBooking::creating(fn () => throw new RuntimeException('retry this batch'));
    try {
        $this->evaluate();
        $this->fail('Expected rollback');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('retry this batch');
    } finally {
        MaddraxikonStreakBooking::flushEventListeners();
    }
    expect($this->points())->toBe($settled)
        ->and(MaddraxikonStreakState::query()->sole()->dirty_from)->toBe($cursor);
    for ($i = 0; $i < 5; $i++) {
        $this->evaluate();
    }
    expect($this->points())->toBe(1010);
    $this->assertDatabaseCount('maddraxikon_streak_bookings', 101);
    expect(MaddraxikonStreakState::query()->sole()->dirty_from)->toBeNull();
});
test('catch up waits for untracked sources and preserves the complete sequence', function (): void {
    config(['maddraxikon.streaks.batch_size' => 1]);
    foreach ([10, 11] as $day) {
        MaddraxikonContribution::factory()->create(['user_id' => $this->member->id, 'account_link_id' => $this->link->id, 'wiki_user_id' => $this->link->wiki_user_id, 'occurred_at' => "2026-10-{$day}T12:00:00+02:00"]);
    }
    expect($this->evaluate())->toBe(0)
        ->and($this->points())->toBe(0);
    $this->evaluate();
    $this->evaluate();
    expect($this->points())->toBe(3)
        ->and(app(MaddraxikonStreakService::class)->reconcile($this->member->id))->toBe(0);
    $day = MaddraxikonStreakDay::query()->orderBy('activity_date')->first();
    expect($day->bookings()->sole()->user->id)->toBe($this->member->id)
        ->and(UserPoint::query()->orderBy('id')->first()->maddraxikonStreakBooking->streak_day_id)->toBe($day->id);
});
test('manual recalculation respects pause and coverage and uses frozen start', function (): void {
    $this->source('2026-10-01T00:00:00+02:00')->forceFill(['streak_status' => 'valid'])->save();
    $service = app(MaddraxikonStreakService::class);
    config(['maddraxikon.features.awards_enabled' => false]);
    expect($service->reconcile($this->member->id))->toBe(0);
    config(['maddraxikon.features.awards_enabled' => true]);
    $sync = MaddraxikonSyncState::query()->sole();
    $sync->update(['recovery_required_at' => now()]);
    expect($service->reconcile($this->member->id))->toBe(0);
    $sync->update(['recovery_required_at' => null]);
    config(['maddraxikon.streaks.starts_at' => '2026-10-02T00:00:00+02:00']);
    expect($this->points())->toBe(0)
        ->and($service->reconcile($this->member->id, from: '2026-10-01'))->toBe(1)
        ->and($this->points())->toBe(1);
});
test('incomplete check results never reverse existing points', function (array $revisionResult): void {
    $source = $this->source();
    $this->evaluate();
    $source->fresh()->forceFill(['streak_next_check_epoch' => 0])->save();
    $api = Mockery::mock(MaddraxikonApiClient::class);
    $api->shouldReceive('streakRevisionDetails')->andReturn($revisionResult === [] ? [] : [$source->revision_id => $revisionResult]);
    $api->shouldReceive('streakPageDetails')->andReturn([$source->page_id => ['exists' => true]]);
    $this->app->instance(MaddraxikonApiClient::class, $api);
    $this->expectException(\Throwable::class);
    try {
        $this->evaluate(true);
    } finally {
        expect($this->points())->toBe(1)
            ->and($source->fresh()->streak_status)->toBe('valid');
    }
})->with('MaddraxikonStreakServiceTest.malformedChecks');
test('recovery attestation requires reason and a matching recovery source', function (): void {
    Team::membersTeam()->users()->updateExistingPivot($this->member->id, ['role' => Role::Admin->value]);
    $source = $this->source();
    foreach (['', 'Ein normaler Beitrag ist kein Recovery-Nachweis.'] as $reason) {
        try {
            app(MaddraxikonStreakService::class)->verifyRecoveredSource($source->id, $this->member, $reason);
            $this->fail('This attestation must be rejected');
        } catch (\LogicException) {
            expect($source->fresh()->streak_bot_verified)->toBeFalse();
        }
    }
});
