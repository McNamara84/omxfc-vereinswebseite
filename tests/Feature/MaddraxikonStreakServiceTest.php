<?php

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MaddraxikonStreakServiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $member;

    protected MaddraxikonAccountLink $link;

    protected array $revisionOverrides = [];

    protected array $pageOverrides = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-20T12:00:00+02:00'));
        config([
            'maddraxikon.streaks.enabled' => true,
            'maddraxikon.streaks.starts_at' => '2026-10-01T00:00:00+02:00',
            'maddraxikon.streaks.batch_size' => 100,
            'maddraxikon.features.awards_enabled' => true,
            'maddraxikon.features.sync_enabled' => true,
            'maddraxikon.features.linking_enabled' => true,
        ]);
        $this->member = User::factory()->create();
        Team::membersTeam()->users()->attach($this->member, ['role' => Role::Mitglied->value]);
        $this->link = MaddraxikonAccountLink::factory()->create([
            'user_id' => $this->member->id,
            'verified_at' => '2026-09-30T00:00:00Z',
            'streak_verified_at_epoch' => CarbonImmutable::parse('2026-09-30T00:00:00Z')->timestamp,
        ]);
        MaddraxikonSyncState::factory()->create([
            'wiki_key' => config('maddraxikon.wiki_key'),
            'initial_watermark_at' => '2026-09-29T00:00:00Z',
            'watermark_at' => now(), 'last_succeeded_at' => now(),
        ]);
        $api = Mockery::mock(MaddraxikonApiClient::class);
        $api->shouldReceive('streakRevisionDetails')->andReturnUsing(function (array $ids): array {
            return MaddraxikonContribution::query()->whereIn('revision_id', $ids)->get()->mapWithKeys(fn ($source) => [$source->revision_id => [
                'exists' => true, 'page_id' => $source->page_id, 'user_id' => $source->wiki_user_id,
                'tags' => [], ...($this->revisionOverrides[$source->revision_id] ?? []),
            ]])->all();
        });
        $api->shouldReceive('streakPageDetails')->andReturnUsing(fn (array $ids) => collect($ids)->mapWithKeys(fn ($id) => [$id => [
            'exists' => true, 'namespace_id' => 0, 'redirect' => false, 'size' => 1, ...($this->pageOverrides[$id] ?? []),
        ]])->all());
        $this->app->instance(MaddraxikonApiClient::class, $api);
    }

    protected function source(string $at = '2026-10-10T12:00:00+02:00', array $attributes = []): MaddraxikonContribution
    {
        $source = MaddraxikonContribution::factory()->create([
            'user_id' => $this->member->id, 'account_link_id' => $this->link->id,
            'wiki_user_id' => $this->link->wiki_user_id, 'wiki_username' => $this->link->wiki_username,
            'occurred_at' => CarbonImmutable::parse($at), ...$attributes,
        ]);
        app(MaddraxikonStreakTracker::class)->record($source, $this->link);

        return $source->fresh();
    }

    protected function evaluate(bool $recheck = false): int
    {
        return app(MaddraxikonStreakService::class)->run($recheck);
    }

    protected function points(): int
    {
        return (int) UserPoint::query()->where('user_id', $this->member->id)->where('team_id', Team::membersTeam()->id)->sum('points');
    }

    public function test_five_days_restart_and_duplicate_runs(): void
    {
        foreach ([10, 11, 12, 13, 14, 16] as $day) {
            $this->source("2026-10-{$day}T12:00:00+02:00");
        }
        $this->assertSame(6, $this->evaluate());
        $this->assertSame([1, 2, 3, 4, 5, 1], MaddraxikonStreakDay::query()->orderBy('activity_date')->pluck('settled_points')->all());
        $this->assertSame(16, $this->points());
        $this->assertSame(0, $this->evaluate());
        $this->assertSame(0, $this->evaluate(true));
        $this->assertDatabaseCount('maddraxikon_streak_bookings', 6);
    }

    public function test_multiple_small_edits_and_article_pay_only_one_daily_bonus_outside_regular_cap(): void
    {
        $this->source(attributes: ['minor' => true, 'old_size' => 600, 'new_size' => 590]);
        $this->source(attributes: ['type' => 'new', 'new_size' => 1]);
        app(BaxxBookingWriter::class)->record($this->member->id, Team::membersTeam()->id, 10);
        $this->assertSame(1, $this->evaluate());
        $this->assertSame(11, $this->points());
        $this->assertDatabaseCount('maddraxikon_reward_events', 0);
        $this->assertDatabaseHas('maddraxikon_streak_days', ['valid_count' => 2, 'settled_points' => 1]);
    }

    public function test_thirty_days_have_no_bonus_cap(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02T12:00:00+01:00'));
        MaddraxikonSyncState::query()->first()->update(['watermark_at' => now(), 'last_succeeded_at' => now()]);
        for ($day = 1; $day <= 30; $day++) {
            $this->source(sprintf('2026-10-%02dT12:00:00+02:00', $day));
        }
        $this->assertSame(30, $this->evaluate());
        $this->assertSame(465, $this->points());
    }

    public function test_reverted_middle_day_recalculates_followers_and_restoration_is_idempotent(): void
    {
        $sources = [];
        foreach ([10, 11, 12, 13, 14] as $day) {
            $sources[] = $this->source("2026-10-{$day}T12:00:00+02:00");
        }
        $this->evaluate();
        $this->revisionOverrides[$sources[2]->revision_id] = ['tags' => ['mw-reverted']];
        MaddraxikonContribution::query()->update(['streak_next_check_epoch' => 0]);
        $this->assertSame(3, $this->evaluate(true));
        $this->assertSame(6, $this->points());
        $this->assertSame([1, 2, 0, 1, 2], MaddraxikonStreakDay::query()->orderBy('activity_date')->pluck('settled_points')->all());
        $this->assertSame(0, $this->evaluate(true));
        $this->assertSame(2, app(MaddraxikonStreakSummary::class)->forUser($this->member)['record']);
        $this->revisionOverrides = [];
        MaddraxikonContribution::query()->update(['streak_next_check_epoch' => 0]);
        $this->assertSame(3, $this->evaluate(true));
        $this->assertSame(15, $this->points());
    }

    public function test_other_valid_source_preserves_day_after_deletion(): void
    {
        $source = $this->source();
        $this->source();
        $this->evaluate();
        $this->pageOverrides[$source->page_id] = ['exists' => false];
        MaddraxikonContribution::query()->update(['streak_next_check_epoch' => 0]);
        $this->assertSame(0, $this->evaluate(true));
        $this->assertSame(1, $this->points());
    }

    public function test_late_import_bridges_series_and_pays_only_differences(): void
    {
        $this->source();
        $this->source('2026-10-12T12:00:00+02:00');
        $this->evaluate();
        $this->assertSame(2, $this->points());
        $this->source('2026-10-11T12:00:00+02:00');
        $this->evaluate();
        $this->assertSame(6, $this->points());
    }

    public function test_twenty_four_hours_are_independent_of_session_deadline(): void
    {
        $source = $this->source('2026-10-19T12:01:00+02:00', ['eligible_after' => now()->subDay()]);
        $this->assertSame(0, $this->evaluate());
        $this->assertSame('pending', $source->fresh()->streak_status);
        $this->travel(2)->minutes();
        $source->update(['eligible_after' => now()->addDays(2)]);
        $this->assertSame(1, $this->evaluate());
    }

    public function test_midnight_edits_in_one_session_count_as_two_days(): void
    {
        $this->source('2026-10-10T23:59:00+02:00', ['session_anchor_revision_id' => 123]);
        $this->source('2026-10-11T00:01:00+02:00', ['session_anchor_revision_id' => 123]);
        $this->evaluate();
        $this->assertSame(3, $this->points());
    }

    public function test_pending_predecessor_prevents_premature_smaller_bonus(): void
    {
        $this->source(attributes: ['status_reason' => 'recovery_bot_status_unverifiable']);
        $this->source('2026-10-11T12:00:00+02:00');
        $this->assertSame(0, $this->evaluate());
        $this->assertSame(0, $this->points());
        $this->assertDatabaseHas('maddraxikon_streak_days', ['activity_date' => '2026-10-11', 'streak_length' => null]);
    }

    public function test_recovery_unknown_bot_can_be_resolved_without_touching_regular_rewards(): void
    {
        $source = $this->source(attributes: ['status' => 'rejected', 'status_reason' => 'recovery_bot_status_unverifiable']);
        $this->evaluate();
        $this->assertSame('pending', $source->fresh()->streak_status);
        $source->fresh()->forceFill(['streak_bot_verified' => true, 'streak_next_check_epoch' => 0])->save();
        $this->assertSame(1, $this->evaluate(true));
        $this->assertSame('rejected', $source->fresh()->status->value);
    }

    #[DataProvider('disabledConfigurations')]
    public function test_disabled_feature_never_pays(string $key, mixed $value): void
    {
        $this->source();
        config([$key => $value]);
        $this->assertSame(0, $this->evaluate());
        $this->assertSame(0, $this->points());
    }

    public static function disabledConfigurations(): array
    {
        return [
            ['maddraxikon.streaks.enabled', false],
            ['maddraxikon.features.awards_enabled', false], ['maddraxikon.features.sync_enabled', false],
        ];
    }

    public function test_prelaunch_activity_does_not_count(): void
    {
        $this->source('2026-09-30T12:00:00+02:00');
        $this->source('2026-10-01T00:00:00+02:00');
        $this->assertSame(1, $this->evaluate());
        $this->assertSame(1, $this->points());
    }

    public function test_recovery_or_missing_initial_coverage_prevents_invented_gaps(): void
    {
        $this->source();
        $sync = MaddraxikonSyncState::query()->first();
        $sync->update(['recovery_required_at' => now()]);
        $this->assertSame(0, $this->evaluate());
        $sync->update(['recovery_required_at' => null, 'initial_watermark_at' => now()->subDay()]);
        $this->assertSame(0, $this->evaluate());
    }

    public function test_api_failure_keeps_confirmed_points_and_can_retry(): void
    {
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
            $this->assertSame('temporary failure', $exception->getMessage());
        }
        $this->assertSame(1, $this->points());
        $this->assertDatabaseHas('maddraxikon_contributions', ['streak_status' => 'valid']);
    }

    public function test_bounded_batches_resume_and_unknown_days_do_not_starve_later_days(): void
    {
        config(['maddraxikon.streaks.batch_size' => 1]);
        $this->source(attributes: ['status_reason' => 'recovery_bot_status_unverifiable']);
        $this->source('2026-10-12T12:00:00+02:00');
        for ($i = 0; $i < 5; $i++) {
            $this->evaluate();
        }
        $this->assertSame(1, $this->points());
    }

    public function test_read_only_preview_makes_no_changes(): void
    {
        $source = $this->source();
        $source->forceFill(['streak_status' => 'valid'])->save();
        $this->assertSame(1, app(MaddraxikonStreakService::class)->reconcile($this->member->id, pretend: true));
        $this->assertSame(0, $this->points());
        $this->assertDatabaseHas('maddraxikon_streak_days', ['settled_points' => 0]);
    }

    public function test_start_cannot_be_changed_after_tracking(): void
    {
        $this->source();
        config(['maddraxikon.streaks.starts_at' => '2026-10-02T00:00:00+02:00']);
        $this->expectException(\LogicException::class);
        app(MaddraxikonStreakSettings::class)->pinStart();
    }

    public function test_disconnected_and_ineligible_members_receive_no_new_bonus(): void
    {
        $this->source();
        $this->link->update(['status' => 'disconnected', 'disconnected_at' => now()]);
        $this->assertSame(0, $this->evaluate());
        $this->link->update(['status' => 'active', 'disconnected_at' => null]);
        Team::membersTeam()->users()->updateExistingPivot($this->member->id, ['role' => Role::Anwaerter->value]);
        $this->assertSame(0, $this->evaluate());
    }

    public function test_transaction_rolls_back_when_booking_fails(): void
    {
        $source = $this->source();
        $source->forceFill(['streak_status' => 'valid'])->save();
        MaddraxikonStreakBooking::creating(fn () => throw new RuntimeException('journal failure'));
        try {
            app(MaddraxikonStreakService::class)->reconcile($this->member->id);
            $this->fail('Expected failure');
        } catch (RuntimeException $exception) {
            $this->assertSame('journal failure', $exception->getMessage());
        } finally {
            MaddraxikonStreakBooking::flushEventListeners();
        }
        $this->assertSame(0, $this->points());
        $this->assertDatabaseHas('maddraxikon_streak_days', ['settled_points' => 0]);
        $this->assertNotNull(MaddraxikonStreakState::query()->first()->dirty_from);
    }

    #[DataProvider('invalidSources')]
    public function test_invalid_sources_never_secure_a_day(array $attributes, array $revision, array $page, string $reason): void
    {
        $source = $this->source(attributes: $attributes);
        $this->revisionOverrides[$source->revision_id] = $revision;
        $this->pageOverrides[$source->page_id] = $page;
        $this->evaluate();
        $this->assertSame(0, $this->points());
        $this->assertSame($reason, $source->fresh()->streak_reason);
    }

    public static function invalidSources(): array
    {
        return [
            [['bot' => true], [], [], 'source_hidden_or_bot'],
            [['anonymous' => true], [], [], 'source_hidden_or_bot'],
            [['user_hidden' => true], [], [], 'source_hidden_or_bot'],
            [['namespace_id' => 1], [], [], 'namespace_excluded'],
            [[], ['exists' => false], [], 'revision_missing'],
            [[], [], ['exists' => false], 'revision_missing'],
            [[], ['user_id' => 9999999], [], 'revision_identity_mismatch'],
            [[], ['page_id' => 9999999], [], 'revision_identity_mismatch'],
            [[], ['user_hidden' => true], [], 'revision_hidden'],
            [[], ['sha1_hidden' => true], [], 'revision_hidden'],
            [[], ['text_hidden' => true], [], 'revision_hidden'],
            [[], ['suppressed' => true], [], 'revision_hidden'],
            [[], ['tags' => ['mw-reverted']], [], 'revision_reverted'],
            [['type' => 'new', 'namespace_id' => 10], [], [], 'not_an_article'],
            [['type' => 'new'], [], ['redirect' => true], 'page_is_redirect'],
        ];
    }

    public function test_incomplete_identity_keeps_existing_bonus(): void
    {
        $source = $this->source();
        $this->evaluate();
        MaddraxikonContribution::query()->update(['streak_next_check_epoch' => 0]);
        $this->revisionOverrides[$source->revision_id] = ['user_id' => null];
        try {
            $this->evaluate(true);
            $this->fail('Incomplete response must stay pending');
        } catch (MaddraxikonApiException) {
            $this->assertSame(1, $this->points());
        }
    }

    #[DataProvider('clockChanges')]
    public function test_local_days_across_clock_and_year_changes(string $first, string $second, string $third): void
    {
        config(['maddraxikon.streaks.starts_at' => '2026-01-01T00:00:00+01:00']);
        $this->link->update(['verified_at' => '2025-12-01T00:00:00Z', 'streak_verified_at_epoch' => CarbonImmutable::parse('2025-12-01')->timestamp]);
        $this->travelTo(CarbonImmutable::parse($third)->addDays(3));
        MaddraxikonSyncState::query()->first()->update(['initial_watermark_at' => '2025-12-01', 'watermark_at' => now(), 'last_succeeded_at' => now()]);
        $this->source($first);
        $this->source($second);
        $this->source($third);
        $this->evaluate();
        $this->assertSame(6, $this->points());
    }

    public static function clockChanges(): array
    {
        return [
            ['2026-03-28T23:55:00+01:00', '2026-03-29T03:01:00+02:00', '2026-03-30T00:01:00+02:00'],
            ['2026-10-24T23:55:00+02:00', '2026-10-25T02:30:00+01:00', '2026-10-26T00:01:00+01:00'],
            ['2026-12-31T23:55:00+01:00', '2027-01-01T00:01:00+01:00', '2027-01-02T00:01:00+01:00'],
        ];
    }

    public function test_repeated_autumn_hour_counts_only_once(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-28T12:00:00+01:00'));
        MaddraxikonSyncState::query()->first()->update(['watermark_at' => now(), 'last_succeeded_at' => now()]);
        $this->source('2026-10-25T02:30:00+02:00');
        $this->source('2026-10-25T02:30:00+01:00');
        $this->assertSame(1, $this->evaluate());
        $this->assertSame(1, $this->points());
    }

    public function test_spent_points_and_later_inactivity_do_not_prevent_owed_reversal(): void
    {
        $source = $this->source();
        $this->evaluate();
        app(BaxxBookingWriter::class)->record($this->member->id, Team::membersTeam()->id, -1);
        $source->fresh()->forceFill(['streak_status' => 'invalid', 'streak_reason' => 'revision_reverted'])->save();
        app(MaddraxikonStreakTracker::class)->dirty($source->wiki_key, $this->member->id, $source->streak_activity_date);
        Team::membersTeam()->users()->updateExistingPivot($this->member->id, ['role' => Role::Anwaerter->value]);
        $this->assertSame(1, app(MaddraxikonStreakService::class)->reconcile($this->member->id));
        $this->assertSame(-1, $this->points());
    }

    public function test_only_an_admin_can_attest_recovery_evidence(): void
    {
        $source = $this->source(attributes: ['status_reason' => 'recovery_bot_status_unverifiable']);
        try {
            app(MaddraxikonStreakService::class)->verifyRecoveredSource($source->id, $this->member, 'Manueller Revisionsnachweis');
            $this->fail('Member must not attest evidence');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        Team::membersTeam()->users()->updateExistingPivot($this->member->id, ['role' => Role::Admin->value]);
        app(MaddraxikonStreakService::class)->verifyRecoveredSource($source->id, $this->member, 'Manueller Revisionsnachweis');
        $this->assertTrue($source->fresh()->streak_bot_verified);
        $this->assertSame($this->member->id, $source->fresh()->streak_reviewed_by);
    }

    public function test_preview_command_and_invalid_date_arguments(): void
    {
        $source = $this->source();
        $source->forceFill(['streak_status' => 'valid'])->save();
        $this->artisan('maddraxikon:streaks', ['--user' => $this->member->id, '--from' => '2026-10-10', '--pretend' => true])->expectsOutputToContain('Vorschau: 1')->assertSuccessful();
        $this->assertSame(0, $this->points());
        $this->artisan('maddraxikon:streaks', ['--from' => 'not-a-date'])->assertFailed();
    }

    public function test_reconsent_preserves_import_boundary_but_reconnection_excludes_the_gap(): void
    {
        Mail::fake();
        $service = app(AccountLinkService::class);
        $identity = new MaddraxikonIdentity($this->link->oauth_subject, $this->link->wiki_user_id, $this->link->wiki_username);
        $original = $this->link->streak_verified_at_epoch;
        $this->link = $service->activate($this->member, $identity, 'test', now());
        $this->assertSame($original, $this->link->streak_verified_at_epoch);
        $this->assertSame(now()->timestamp, $this->link->verified_at_epoch);
        $sync = MaddraxikonSyncState::query()->first();
        $sync->update(['watermark_at' => now()->subHours(2)]);
        $at = CarbonImmutable::now()->subHour();
        $change = [
            'type' => 'edit', 'ns' => 0, 'title' => 'Kleine Korrektur', 'pageid' => 42,
            'revid' => 987601, 'old_revid' => 987600, 'rcid' => 987601,
            'user' => $this->link->wiki_username, 'userid' => $this->link->wiki_user_id,
            'oldlen' => 100, 'newlen' => 99, 'timestamp' => $at->utc()->format('Y-m-d\TH:i:s\Z'),
            'minor' => true, 'bot' => false, 'anon' => false, 'tags' => [],
        ];
        app(MaddraxikonApiClient::class)->shouldReceive('recentChanges')->once()->andReturn([$change]);
        $this->assertSame(1, app(MaddraxikonContributionImporter::class)->sync());
        $source = MaddraxikonContribution::query()->sole();
        $this->assertSame($original, $source->streak_verified_at_epoch);
        $this->assertSame('pending', $source->streak_status);
        $this->travelTo($at->addDay());
        $sync->update(['watermark_at' => now(), 'last_succeeded_at' => now()]);
        $this->assertSame(1, $this->evaluate());
        $service->disconnect($this->member);
        $gap = CarbonImmutable::now()->addHour();
        $this->travelTo($gap->addHour());
        $this->link = $service->activate($this->member, $identity, 'test', now());
        $this->assertSame(now()->timestamp, $this->link->streak_verified_at_epoch);
        $this->assertSame($original, $source->fresh()->streak_verified_at_epoch);
        $this->assertSame('excluded', $this->source($gap->toIso8601String())->streak_status);
        $this->assertSame('pending', $this->source(now()->toIso8601String())->streak_status);
        $this->assertSame(1, $this->points());
    }

    public function test_exact_launch_and_verification_boundaries_and_maintenance_import(): void
    {
        config(['maddraxikon.streaks.enabled' => false]);
        $start = app(MaddraxikonStreakSettings::class)->start();
        $before = $this->source($start->subSecond()->toIso8601String());
        $at = $this->source($start->toIso8601String());
        $this->assertNull($before->streak_activity_date);
        $this->assertSame('pending', $at->streak_status);
        $this->assertSame(0, $this->evaluate());
        config(['maddraxikon.streaks.enabled' => true]);
        MaddraxikonRewardPolicy::query()->update(['new_articles_enabled' => false, 'edit_sessions_enabled' => false]);
        $this->assertSame(1, $this->evaluate());
        $this->assertSame(1, $this->points());
    }

    public function test_api_response_without_explicit_missing_markers_preserves_existing_points(): void
    {
        $source = $this->source(attributes: ['type' => 'new']);
        $this->evaluate();
        $source->fresh()->forceFill(['streak_next_check_epoch' => 0])->save();
        $this->pageOverrides[$source->page_id] = ['namespace_id' => null];
        $this->expectException(MaddraxikonApiException::class);
        try {
            $this->evaluate(true);
        } finally {
            $this->assertSame(1, $this->points());
        }
    }

    public function test_jobs_and_command_process_and_recheck_real_bonuses(): void
    {
        $source = $this->source();
        $initial = new EvaluateMaddraxikonStreaks;
        $recheck = new EvaluateMaddraxikonStreaks(true);
        $this->assertNotSame($initial->uniqueId(), $recheck->uniqueId());
        $initial->handle(app(MaddraxikonStreakService::class));
        $this->assertSame(1, $this->points());
        $this->revisionOverrides[$source->revision_id] = ['tags' => ['mw-reverted']];
        $source->fresh()->forceFill(['streak_next_check_epoch' => 0])->save();
        $this->artisan('maddraxikon:streaks', ['--recheck' => true])->expectsOutputToContain('1 Bonusdifferenzen')->assertSuccessful();
        $this->assertSame(0, $this->points());
        $this->artisan('maddraxikon:streaks', ['--user' => 999999999])->assertFailed();
    }

    public function test_recently_successful_but_backlogged_import_does_not_reset_displayed_series(): void
    {
        $this->source('2026-10-17T12:00:00+02:00');
        $this->evaluate();
        MaddraxikonSyncState::query()->first()->update(['watermark_at' => '2026-10-18T12:00:00+02:00', 'last_succeeded_at' => now()]);
        $summary = app(MaddraxikonStreakSummary::class)->forUser($this->member);
        $this->assertTrue($summary['delayed']);
        $this->assertSame(1, $summary['current']);
        $this->assertNull($summary['next']);
        $this->assertSame('unknown', $summary['week'][5]['status']);
    }

    public function test_confirmed_gap_resets_display_even_when_another_old_day_is_pending(): void
    {
        $this->source('2026-10-17T12:00:00+02:00');
        $this->evaluate();
        $this->source('2026-10-18T12:00:00+02:00');
        $summary = app(MaddraxikonStreakSummary::class)->forUser($this->member);
        $this->assertSame(0, $summary['current']);
        $this->assertTrue($summary['pending']);
    }

    public function test_long_correction_resumes_after_failure_without_losing_or_duplicating_points(): void
    {
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
        $this->assertSame(1830, $this->points());
        $source = $sources[19]->fresh();
        $source->forceFill(['streak_next_check_epoch' => 0])->save();
        $this->revisionOverrides[$source->revision_id] = ['tags' => ['mw-reverted']];
        $this->assertSame(10, $this->evaluate(true));
        $settled = $this->points();
        $cursor = MaddraxikonStreakState::query()->sole()->dirty_from;
        MaddraxikonStreakBooking::creating(fn () => throw new RuntimeException('retry this batch'));
        try {
            $this->evaluate();
            $this->fail('Expected rollback');
        } catch (RuntimeException $exception) {
            $this->assertSame('retry this batch', $exception->getMessage());
        } finally {
            MaddraxikonStreakBooking::flushEventListeners();
        }
        $this->assertSame($settled, $this->points());
        $this->assertSame($cursor, MaddraxikonStreakState::query()->sole()->dirty_from);
        for ($i = 0; $i < 5; $i++) {
            $this->evaluate();
        }
        $this->assertSame(1010, $this->points());
        $this->assertDatabaseCount('maddraxikon_streak_bookings', 101);
        $this->assertNull(MaddraxikonStreakState::query()->sole()->dirty_from);
    }

    public function test_catch_up_waits_for_untracked_sources_and_preserves_the_complete_sequence(): void
    {
        config(['maddraxikon.streaks.batch_size' => 1]);
        foreach ([10, 11] as $day) {
            MaddraxikonContribution::factory()->create([
                'user_id' => $this->member->id, 'account_link_id' => $this->link->id,
                'wiki_user_id' => $this->link->wiki_user_id, 'occurred_at' => "2026-10-{$day}T12:00:00+02:00",
            ]);
        }
        $this->assertSame(0, $this->evaluate());
        $this->assertSame(0, $this->points());
        $this->evaluate();
        $this->evaluate();
        $this->assertSame(3, $this->points());
        $this->assertSame(0, app(MaddraxikonStreakService::class)->reconcile($this->member->id));
        $day = MaddraxikonStreakDay::query()->orderBy('activity_date')->first();
        $this->assertSame($this->member->id, $day->bookings()->sole()->user->id);
        $this->assertSame($day->id, UserPoint::query()->orderBy('id')->first()->maddraxikonStreakBooking->streak_day_id);
    }

    public function test_manual_recalculation_cannot_bypass_pause_coverage_or_frozen_start(): void
    {
        $this->source()->forceFill(['streak_status' => 'valid'])->save();
        $service = app(MaddraxikonStreakService::class);
        config(['maddraxikon.features.awards_enabled' => false]);
        $this->assertSame(0, $service->reconcile($this->member->id));
        config(['maddraxikon.features.awards_enabled' => true]);
        $sync = MaddraxikonSyncState::query()->sole();
        $sync->update(['recovery_required_at' => now()]);
        $this->assertSame(0, $service->reconcile($this->member->id));
        $sync->update(['recovery_required_at' => null]);
        config(['maddraxikon.streaks.starts_at' => '2026-10-02T00:00:00+02:00']);
        $this->assertSame(0, $this->points());
        $this->expectException(\LogicException::class);
        $service->reconcile($this->member->id, from: '2026-10-01');
    }

    #[DataProvider('malformedChecks')]
    public function test_incomplete_check_results_never_reverse_existing_points(array $revisionResult): void
    {
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
            $this->assertSame(1, $this->points());
            $this->assertSame('valid', $source->fresh()->streak_status);
        }
    }

    public static function malformedChecks(): array
    {
        return [[[]], [['user_id' => 7]]];
    }

    public function test_recovery_attestation_requires_reason_and_a_matching_recovery_source(): void
    {
        Team::membersTeam()->users()->updateExistingPivot($this->member->id, ['role' => Role::Admin->value]);
        $source = $this->source();
        foreach (['', 'Ein normaler Beitrag ist kein Recovery-Nachweis.'] as $reason) {
            try {
                app(MaddraxikonStreakService::class)->verifyRecoveredSource($source->id, $this->member, $reason);
                $this->fail('This attestation must be rejected');
            } catch (\LogicException) {
                $this->assertFalse($source->fresh()->streak_bot_verified);
            }
        }
    }
}
