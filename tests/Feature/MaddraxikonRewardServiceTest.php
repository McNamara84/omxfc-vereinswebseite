<?php

namespace Tests\Feature;

use App\Enums\MaddraxikonAccountLinkStatus;
use App\Enums\MaddraxikonContributionStatus;
use App\Enums\MaddraxikonContributionType;
use App\Enums\MaddraxikonRewardEventStatus;
use App\Enums\Role;
use App\Exceptions\MaddraxikonApiException;
use App\Models\BaxxEarningRule;
use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonContribution;
use App\Models\MaddraxikonRewardEvent;
use App\Models\MaddraxikonSyncState;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPoint;
use App\Services\Maddraxikon\Exceptions\RecoveryRequiredException;
use App\Services\Maddraxikon\MaddraxikonApiClient;
use App\Services\Maddraxikon\MaddraxikonRewardService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use LogicException;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

#[CoversClass(MaddraxikonRewardService::class)]
class MaddraxikonRewardServiceTest extends TestCase
{
    use RefreshDatabase;

    private int $nextExternalId = 10_000;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'maddraxikon.features.awards_enabled' => true,
            'maddraxikon.features.sync_enabled' => true,
            'maddraxikon.daily_point_cap' => 10,
            'maddraxikon.minimum_article_bytes' => 500,
            'maddraxikon.article_namespace' => 0,
            'maddraxikon.session_window_minutes' => 30,
            'maddraxikon.timezone' => 'Europe/Berlin',
            'maddraxikon.evaluation.source_batch_size' => 100,
            'maddraxikon.evaluation.api_batch_size' => 50,
        ]);

        BaxxEarningRule::query()->updateOrCreate(
            ['action_key' => MaddraxikonRewardEvent::ACTION_EDIT_SESSION],
            [
                'label' => 'Maddraxikon-Bearbeitungen',
                'description' => 'Eine Baxx je fünf Bearbeitungssitzungen.',
                'points' => 1,
                'every_count' => 5,
                'is_active' => true,
            ]
        );
        BaxxEarningRule::query()->updateOrCreate(
            ['action_key' => MaddraxikonRewardEvent::ACTION_NEW_ARTICLE],
            [
                'label' => 'Maddraxikon-Artikel',
                'description' => 'Fünf Baxx je neuem qualifiziertem Artikel.',
                'points' => 5,
                'every_count' => 1,
                'is_active' => true,
            ]
        );
    }

    public function test_feature_flag_prevents_evaluation_without_api_calls(): void
    {
        config(['maddraxikon.features.awards_enabled' => false]);
        [, $link] = $this->linkedMember();
        $this->contribution($link, ['type' => MaddraxikonContributionType::New]);

        $api = Mockery::mock(MaddraxikonApiClient::class);
        $api->shouldNotReceive('revisionDetails');
        $api->shouldNotReceive('pageDetails');

        $this->assertSame(0, $this->service($api)->evaluate());
        $this->assertDatabaseCount('maddraxikon_reward_events', 0);
    }

    public function test_evaluation_fails_closed_when_awards_are_enabled_without_import(): void
    {
        config(['maddraxikon.features.sync_enabled' => false]);
        [, $link] = $this->linkedMember();
        $this->contribution($link, [
            'type' => MaddraxikonContributionType::New,
        ]);

        $api = Mockery::mock(MaddraxikonApiClient::class);
        $api->shouldNotReceive('revisionDetails');
        $api->shouldNotReceive('pageDetails');

        try {
            $this->service($api)->evaluate();
            $this->fail(
                'Auszahlungen ohne aktiven Import müssen gesperrt sein.'
            );
        } catch (LogicException $exception) {
            $this->assertStringContainsString(
                'aktiven Import',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseCount('maddraxikon_reward_events', 0);
    }

    public function test_open_recovery_window_blocks_all_evaluation_even_when_forced(): void
    {
        [, $link] = $this->linkedMember();
        $this->contribution($link, [
            'type' => MaddraxikonContributionType::New,
        ]);
        MaddraxikonSyncState::factory()->create([
            'recovery_required_at' => now(),
            'recovery_from_at' => now()->subDays(31),
            'recovery_until_at' => now(),
        ]);
        $api = Mockery::mock(MaddraxikonApiClient::class);
        $api->shouldNotReceive('revisionDetails');
        $api->shouldNotReceive('pageDetails');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('offenen Recovery-Fensters gesperrt');

        $this->service($api)->evaluate(force: true);
    }

    public function test_every_fifth_edit_session_awards_one_baxx_once(): void
    {
        [$user, $link] = $this->linkedMember();
        $contributions = collect();

        foreach (range(1, 5) as $index) {
            $contributions->push($this->contribution($link, [
                'page_id' => 20_000 + $index,
                'page_title' => 'Artikel '.$index,
                'occurred_at' => now()->subHours(30)->addMinutes($index),
            ]));
        }

        $this->completeWatermark();
        $api = $this->apiWithValidRevisions($contributions);
        $service = $this->service($api);

        $this->assertSame(5, $service->evaluate());
        $this->assertSame(0, $service->evaluate());
        $this->assertDatabaseCount('maddraxikon_reward_events', 5);
        $this->assertDatabaseHas('maddraxikon_reward_events', [
            'user_id' => $user->id,
            'sequence_number' => 5,
            'candidate_points' => 1,
            'awarded_points' => 1,
            'status' => MaddraxikonRewardEventStatus::Awarded->value,
        ]);
        $this->assertDatabaseHas('user_points', [
            'user_id' => $user->id,
            'team_id' => Team::membersTeam()->id,
            'points' => 1,
        ]);
    }

    public function test_edit_session_counts_once_and_rejects_only_invalid_revisions(): void
    {
        [, $link] = $this->linkedMember();
        BaxxEarningRule::query()
            ->where('action_key', MaddraxikonRewardEvent::ACTION_EDIT_SESSION)
            ->update(['every_count' => 1]);

        $first = $this->contribution($link, [
            'page_id' => 123,
            'occurred_at' => now()->subHours(30),
        ]);
        $second = $this->contribution($link, [
            'page_id' => 123,
            'occurred_at' => now()->subHours(30)->addMinutes(30),
            'session_anchor_revision_id' => $first->revision_id,
        ]);
        $first->update(['session_anchor_revision_id' => $first->revision_id]);
        $this->completeWatermark();

        $api = Mockery::mock(MaddraxikonApiClient::class);
        $api->shouldReceive('revisionDetails')->once()->andReturn([
            $first->revision_id => $this->revisionFor($first, ['tags' => ['mw-reverted']]),
            $second->revision_id => $this->revisionFor($second),
        ]);

        $this->assertSame(1, $this->service($api)->evaluate());
        $this->assertDatabaseCount('maddraxikon_reward_events', 1);
        $this->assertDatabaseHas('maddraxikon_contributions', [
            'id' => $first->id,
            'status' => MaddraxikonContributionStatus::Rejected->value,
            'status_reason' => 'revision_reverted',
        ]);
        $this->assertDatabaseHas('maddraxikon_contributions', [
            'id' => $second->id,
            'status' => MaddraxikonContributionStatus::Awarded->value,
        ]);
    }

    public function test_all_reverted_session_revisions_are_rejected_without_sequence(): void
    {
        [, $link] = $this->linkedMember();
        $contribution = $this->contribution($link);
        $contribution->update([
            'session_anchor_revision_id' => $contribution->revision_id,
        ]);
        $this->completeWatermark();

        $api = Mockery::mock(MaddraxikonApiClient::class);
        $api->shouldReceive('revisionDetails')->once()->andReturn([
            $contribution->revision_id => $this->revisionFor(
                $contribution,
                ['tags' => ['mw-reverted']]
            ),
        ]);

        $this->assertSame(1, $this->service($api)->evaluate());
        $event = MaddraxikonRewardEvent::query()->sole();
        $this->assertSame(MaddraxikonRewardEventStatus::Rejected, $event->status);
        $this->assertNull($event->sequence_number);
        $this->assertSame('revision_reverted', $event->status_reason);
    }

    public function test_qualified_new_article_awards_five_baxx(): void
    {
        [$user, $link] = $this->linkedMember();
        $article = $this->contribution($link, [
            'type' => MaddraxikonContributionType::New,
            'parent_revision_id' => 0,
        ]);
        $api = $this->apiForNewArticle($article);

        $this->assertSame(1, $this->service($api)->evaluate());
        $this->assertDatabaseHas('maddraxikon_reward_events', [
            'source_key' => 'new:'.$article->revision_id,
            'candidate_points' => 5,
            'awarded_points' => 5,
            'status' => MaddraxikonRewardEventStatus::Awarded->value,
        ]);
        $this->assertDatabaseHas('user_points', [
            'user_id' => $user->id,
            'points' => 5,
        ]);
    }

    #[DataProvider('invalidArticleProvider')]
    public function test_invalid_new_article_is_rejected(
        array $revisionOverrides,
        array $pageOverrides,
        string $expectedReason
    ): void {
        [, $link] = $this->linkedMember();
        $article = $this->contribution($link, [
            'type' => MaddraxikonContributionType::New,
            'parent_revision_id' => 0,
        ]);
        $api = $this->apiForNewArticle(
            $article,
            $revisionOverrides,
            $pageOverrides
        );

        $this->assertSame(1, $this->service($api)->evaluate());
        $this->assertDatabaseHas('maddraxikon_reward_events', [
            'source_key' => 'new:'.$article->revision_id,
            'status' => MaddraxikonRewardEventStatus::Rejected->value,
            'status_reason' => $expectedReason,
            'awarded_points' => 0,
        ]);
        $this->assertDatabaseCount('user_points', 0);
    }

    public static function invalidArticleProvider(): array
    {
        return [
            'revision reverted' => [
                ['tags' => ['mw-reverted']],
                [],
                'revision_reverted',
            ],
            'page deleted' => [
                [],
                ['exists' => false],
                'page_missing',
            ],
            'redirect' => [
                [],
                ['redirect' => true],
                'page_is_redirect',
            ],
            'too short' => [
                [],
                ['size' => 499],
                'article_too_short',
            ],
            'wrong namespace' => [
                [],
                ['namespace_id' => 10],
                'not_an_article',
            ],
            'SHA-1 hidden by revision deletion' => [
                ['sha1_hidden' => true],
                [],
                'revision_hidden',
            ],
            'text hidden by revision deletion' => [
                ['text_hidden' => true],
                [],
                'revision_hidden',
            ],
            'author suppressed' => [
                ['user_hidden' => true, 'suppressed' => true],
                [],
                'revision_hidden',
            ],
        ];
    }

    public function test_new_article_must_have_originated_in_the_article_namespace(): void
    {
        [, $link] = $this->linkedMember();
        $article = $this->contribution($link, [
            'type' => MaddraxikonContributionType::New,
            'parent_revision_id' => 0,
            'namespace_id' => 10,
        ]);
        $api = Mockery::mock(MaddraxikonApiClient::class);
        $api->shouldNotReceive('revisionDetails');
        $api->shouldNotReceive('pageDetails');

        $this->assertSame(1, $this->service($api)->evaluate());
        $this->assertDatabaseHas('maddraxikon_reward_events', [
            'source_key' => 'new:'.$article->revision_id,
            'status' => MaddraxikonRewardEventStatus::Rejected->value,
            'status_reason' => 'source_namespace_not_article',
            'awarded_points' => 0,
        ]);
        $this->assertDatabaseHas('maddraxikon_contributions', [
            'id' => $article->id,
            'status' => MaddraxikonContributionStatus::Rejected->value,
        ]);
    }

    public function test_daily_cap_is_partially_applied_and_overflow_expires(): void
    {
        [$user, $link] = $this->linkedMember();
        $first = $this->contribution($link, [
            'type' => MaddraxikonContributionType::New,
            'occurred_at' => now()->subHours(30),
        ]);
        $activityDate = $first->occurred_at
            ->timezone('Europe/Berlin')
            ->toDateString();

        MaddraxikonRewardEvent::query()->create([
            'wiki_key' => $first->wiki_key,
            'user_id' => $user->id,
            'account_link_id' => $link->id,
            'source_contribution_id' => null,
            'action_key' => MaddraxikonRewardEvent::ACTION_NEW_ARTICLE,
            'source_key' => 'new:prior',
            'source_revision_id' => 9_999,
            'activity_date' => $activityDate,
            'sequence_number' => 1,
            'rule_points' => 8,
            'rule_every_count' => 1,
            'candidate_points' => 8,
            'awarded_points' => 8,
            'capped_points' => 0,
            'status' => MaddraxikonRewardEventStatus::Awarded,
            'awarded_at' => now(),
        ]);

        $api = $this->apiForNewArticle($first);
        $this->assertSame(1, $this->service($api)->evaluate());
        $this->assertDatabaseHas('maddraxikon_reward_events', [
            'source_key' => 'new:'.$first->revision_id,
            'candidate_points' => 5,
            'awarded_points' => 2,
            'capped_points' => 3,
            'status_reason' => 'daily_cap_partially_applied',
        ]);

        $nextDay = $this->contribution($link, [
            'type' => MaddraxikonContributionType::New,
            'occurred_at' => $first->occurred_at->copy()->addDay(),
        ]);
        $api = $this->apiForNewArticle($nextDay);
        $this->assertSame(1, $this->service($api)->evaluate());
        $this->assertDatabaseHas('maddraxikon_reward_events', [
            'source_key' => 'new:'.$nextDay->revision_id,
            'awarded_points' => 5,
            'capped_points' => 0,
        ]);
    }

    public function test_reversed_award_does_not_restore_daily_cap(): void
    {
        [$user, $link] = $this->linkedMember();
        $article = $this->contribution($link, [
            'type' => MaddraxikonContributionType::New,
        ]);
        $service = $this->service($this->apiForNewArticle($article));
        $service->evaluate();
        $admin = $this->admin();
        $service->reverse(
            MaddraxikonRewardEvent::query()->sole(),
            $admin,
            'Missbrauch bestätigt'
        );

        $next = $this->contribution($link, [
            'type' => MaddraxikonContributionType::New,
            'occurred_at' => $article->occurred_at->copy()->addMinute(),
        ]);
        $this->service($this->apiForNewArticle($next))->evaluate();

        $this->assertSame(
            10,
            (int) MaddraxikonRewardEvent::query()
                ->where('user_id', $user->id)
                ->sum('awarded_points')
        );
        $this->assertSame(
            5,
            (int) UserPoint::query()
                ->where('user_id', $user->id)
                ->sum('points')
        );
    }

    public function test_disconnected_link_before_evaluation_is_rejected_without_api(): void
    {
        [, $link] = $this->linkedMember();
        $article = $this->contribution($link, [
            'type' => MaddraxikonContributionType::New,
        ]);
        $link->update([
            'status' => MaddraxikonAccountLinkStatus::Disconnected,
            'disconnected_at' => now(),
        ]);
        $api = Mockery::mock(MaddraxikonApiClient::class);
        $api->shouldNotReceive('revisionDetails');
        $api->shouldNotReceive('pageDetails');

        $this->assertSame(1, $this->service($api)->evaluate());
        $this->assertDatabaseHas('maddraxikon_contributions', [
            'id' => $article->id,
            'status' => MaddraxikonContributionStatus::Rejected->value,
            'status_reason' => 'account_link_inactive',
        ]);
    }

    public function test_applicant_is_not_eligible_for_award(): void
    {
        [$user, $link] = $this->linkedMember();
        Team::membersTeam()->users()->updateExistingPivot(
            $user->id,
            ['role' => Role::Anwaerter->value]
        );
        $article = $this->contribution($link, [
            'type' => MaddraxikonContributionType::New,
        ]);
        $api = Mockery::mock(MaddraxikonApiClient::class);
        $api->shouldNotReceive('revisionDetails');

        $this->assertSame(1, $this->service($api)->evaluate());
        $this->assertDatabaseHas('maddraxikon_reward_events', [
            'source_key' => 'new:'.$article->revision_id,
            'status_reason' => 'membership_inactive',
        ]);
    }

    public function test_future_due_date_and_incomplete_session_watermark_remain_pending(): void
    {
        [, $link] = $this->linkedMember();
        $future = $this->contribution($link, [
            'type' => MaddraxikonContributionType::New,
            'eligible_after' => now()->addMinute(),
        ]);
        $edit = $this->contribution($link, [
            'occurred_at' => now()->subHours(30),
        ]);
        $edit->update(['session_anchor_revision_id' => $edit->revision_id]);
        MaddraxikonSyncState::query()->create([
            'wiki_key' => $edit->wiki_key,
            'watermark_at' => $edit->occurred_at->copy()->addMinutes(29),
        ]);
        $api = Mockery::mock(MaddraxikonApiClient::class);
        $api->shouldNotReceive('revisionDetails');
        $api->shouldNotReceive('pageDetails');

        $this->assertSame(0, $this->service($api)->evaluate());
        $this->assertSame(MaddraxikonContributionStatus::Pending, $future->fresh()->status);
        $this->assertSame(MaddraxikonContributionStatus::Pending, $edit->fresh()->status);
    }

    public function test_inactive_rule_consumes_sequence_without_award(): void
    {
        [$user, $link] = $this->linkedMember();
        $rule = BaxxEarningRule::query()
            ->where('action_key', MaddraxikonRewardEvent::ACTION_EDIT_SESSION)
            ->firstOrFail();
        $rule->update(['every_count' => 2, 'is_active' => false]);
        $first = $this->contribution($link, ['page_id' => 100]);
        $first->update(['session_anchor_revision_id' => $first->revision_id]);
        $this->completeWatermark();

        $this->service($this->apiWithValidRevisions(collect([$first])))->evaluate();
        $this->assertDatabaseHas('maddraxikon_reward_events', [
            'source_key' => 'edit-session:'.$first->revision_id,
            'sequence_number' => 1,
            'awarded_points' => 0,
            'status_reason' => 'rule_inactive',
        ]);

        $rule->update(['is_active' => true]);
        $second = $this->contribution($link, ['page_id' => 101]);
        $second->update(['session_anchor_revision_id' => $second->revision_id]);
        $this->completeWatermark();
        $this->service($this->apiWithValidRevisions(collect([$second])))->evaluate();

        $this->assertDatabaseHas('maddraxikon_reward_events', [
            'source_key' => 'edit-session:'.$second->revision_id,
            'sequence_number' => 2,
            'awarded_points' => 1,
        ]);
        $this->assertSame(1, (int) UserPoint::query()->where('user_id', $user->id)->sum('points'));
    }

    public function test_edit_sessions_are_sequenced_by_last_activity_then_revision(): void
    {
        [, $link] = $this->linkedMember();
        $start = now()->subHours(30);
        $longSession = $this->contribution($link, [
            'page_id' => 900,
            'occurred_at' => $start,
        ]);
        $longSession->update([
            'session_anchor_revision_id' => $longSession->revision_id,
        ]);
        $longSessionMiddle = $this->contribution($link, [
            'page_id' => 900,
            'occurred_at' => $start->copy()->addMinutes(25),
            'session_anchor_revision_id' => $longSession->revision_id,
        ]);
        $longSessionLast = $this->contribution($link, [
            'page_id' => 900,
            'occurred_at' => $start->copy()->addMinutes(50),
            'session_anchor_revision_id' => $longSession->revision_id,
        ]);
        $shortSessions = collect();

        foreach ([10, 20, 30, 40] as $index => $minutes) {
            $short = $this->contribution($link, [
                'page_id' => 910 + $index,
                'occurred_at' => $start->copy()->addMinutes($minutes),
            ]);
            $short->update([
                'session_anchor_revision_id' => $short->revision_id,
            ]);
            $shortSessions->push($short);
        }

        $this->completeWatermark();
        $allContributions = collect([
            $longSession,
            $longSessionMiddle,
            $longSessionLast,
            ...$shortSessions->all(),
        ]);
        $this->service(
            $this->apiWithValidRevisions($allContributions),
        )->evaluate();

        $this->assertDatabaseHas('maddraxikon_reward_events', [
            'source_key' => 'edit-session:'.$longSession->revision_id,
            'sequence_number' => 5,
            'awarded_points' => 1,
        ]);
        $this->assertDatabaseHas('maddraxikon_reward_events', [
            'source_key' => 'edit-session:'.$shortSessions->last()->revision_id,
            'sequence_number' => 4,
            'awarded_points' => 0,
        ]);
    }

    public function test_activity_date_uses_berlin_timezone(): void
    {
        [, $link] = $this->linkedMember();
        $activityAt = CarbonImmutable::parse('2026-07-18 23:30:00', 'UTC');
        $article = $this->contribution($link, [
            'type' => MaddraxikonContributionType::New,
            'occurred_at' => $activityAt->setTimezone(config('app.timezone')),
            'eligible_after' => $activityAt->addDay()->setTimezone(config('app.timezone')),
        ]);
        $this->travelTo($activityAt->addDay()->addMinute());

        $this->service($this->apiForNewArticle($article))->evaluate();

        $this->assertSame(
            '2026-07-19',
            MaddraxikonRewardEvent::query()
                ->where('source_key', 'new:'.$article->revision_id)
                ->sole()->activity_date->toDateString()
        );
    }

    public function test_api_failure_leaves_contribution_pending_and_writes_no_ledger(): void
    {
        [, $link] = $this->linkedMember();
        $article = $this->contribution($link, [
            'type' => MaddraxikonContributionType::New,
        ]);
        $api = Mockery::mock(MaddraxikonApiClient::class);
        $api->shouldReceive('revisionDetails')
            ->once()
            ->andThrow(new MaddraxikonApiException('nicht erreichbar'));

        try {
            $this->service($api)->evaluate();
            $this->fail(
                'Ein systemischer API-Fehler muss den Queue-Retry auslösen.'
            );
        } catch (MaddraxikonApiException $exception) {
            $this->assertSame(
                'nicht erreichbar',
                $exception->getMessage()
            );
        }

        $article->refresh();
        $this->assertSame(MaddraxikonContributionStatus::Pending, $article->status);
        $this->assertSame(1, $article->evaluation_attempts);
        $this->assertStringContainsString(
            'MaddraxikonApiException: nicht erreichbar',
            $article->last_evaluation_error
        );
        $this->assertNotNull($article->last_evaluation_error_at);
        $this->assertDatabaseCount('maddraxikon_reward_events', 0);
    }

    public function test_evaluation_run_limits_sources_without_splitting_edit_sessions(): void
    {
        config(['maddraxikon.evaluation.source_batch_size' => 2]);
        [, $link] = $this->linkedMember();
        $startedAt = now()->subHours(30);
        $first = $this->contribution($link, [
            'page_id' => 501,
            'occurred_at' => $startedAt,
        ]);
        $firstFollowUp = $this->contribution($link, [
            'page_id' => 501,
            'occurred_at' => $startedAt->copy()->addMinutes(10),
            'session_anchor_revision_id' => $first->revision_id,
        ]);
        $second = $this->contribution($link, [
            'page_id' => 502,
            'occurred_at' => $startedAt->copy()->addMinutes(20),
        ]);
        $third = $this->contribution($link, [
            'page_id' => 503,
            'occurred_at' => $startedAt->copy()->addMinutes(30),
        ]);
        $this->completeWatermark();

        $api = Mockery::mock(MaddraxikonApiClient::class);
        $api->shouldReceive('revisionDetails')
            ->once()
            ->with([
                $first->revision_id,
                $firstFollowUp->revision_id,
                $second->revision_id,
            ])
            ->andReturn([
                $first->revision_id => $this->revisionFor($first),
                $firstFollowUp->revision_id => $this->revisionFor($firstFollowUp),
                $second->revision_id => $this->revisionFor($second),
            ]);
        $api->shouldNotReceive('pageDetails');

        $this->assertSame(2, $this->service($api)->evaluate());
        $this->assertDatabaseCount('maddraxikon_reward_events', 2);
        $this->assertNotSame(
            MaddraxikonContributionStatus::Pending,
            $first->fresh()->status
        );
        $this->assertNotSame(
            MaddraxikonContributionStatus::Pending,
            $firstFollowUp->fresh()->status
        );
        $this->assertNotSame(
            MaddraxikonContributionStatus::Pending,
            $second->fresh()->status
        );
        $this->assertSame(
            MaddraxikonContributionStatus::Pending,
            $third->fresh()->status
        );
    }

    public function test_remote_validation_is_bundled_into_configured_chunks(): void
    {
        config(['maddraxikon.evaluation.api_batch_size' => 2]);
        [, $link] = $this->linkedMember();
        $articles = collect();
        $startedAt = now()->subHours(30);

        foreach (range(1, 5) as $index) {
            $articles->push($this->contribution($link, [
                'type' => MaddraxikonContributionType::New,
                'parent_revision_id' => 0,
                'occurred_at' => $startedAt->copy()->addMinutes($index),
            ]));
        }

        $byRevision = $articles->keyBy('revision_id');
        $byPage = $articles->keyBy('page_id');
        $revisionBatches = [];
        $pageBatches = [];
        $api = Mockery::mock(MaddraxikonApiClient::class);
        $api->shouldReceive('revisionDetails')
            ->times(3)
            ->andReturnUsing(function (array $ids) use (
                &$revisionBatches,
                $byRevision
            ): array {
                $revisionBatches[] = $ids;

                return collect($ids)
                    ->mapWithKeys(fn (int $id): array => [
                        $id => $this->revisionFor($byRevision->get($id)),
                    ])
                    ->all();
            });
        $api->shouldReceive('pageDetails')
            ->times(3)
            ->andReturnUsing(function (array $ids) use (
                &$pageBatches,
                $byPage
            ): array {
                $pageBatches[] = $ids;

                return collect($ids)
                    ->mapWithKeys(function (int $id) use ($byPage): array {
                        $article = $byPage->get($id);

                        return [
                            $id => [
                                'exists' => true,
                                'page_id' => $id,
                                'namespace_id' => 0,
                                'title' => $article->page_title,
                                'size' => 500,
                                'redirect' => false,
                            ],
                        ];
                    })
                    ->all();
            });

        $this->assertSame(5, $this->service($api)->evaluate());
        $this->assertSame(
            array_chunk($articles->pluck('revision_id')->all(), 2),
            $revisionBatches
        );
        $this->assertSame(
            array_chunk($articles->pluck('page_id')->all(), 2),
            $pageBatches
        );
        $this->assertDatabaseCount('maddraxikon_reward_events', 5);
    }

    public function test_api_failure_opens_circuit_for_other_users_in_same_run(): void
    {
        config(['maddraxikon.evaluation.api_batch_size' => 1]);
        [, $firstLink] = $this->linkedMember();
        [, $secondLink] = $this->linkedMember();
        $older = $this->contribution($firstLink, [
            'type' => MaddraxikonContributionType::New,
            'occurred_at' => now()->subHours(31),
            'eligible_after' => now()->subHours(7),
        ]);
        $newer = $this->contribution($secondLink, [
            'type' => MaddraxikonContributionType::New,
            'occurred_at' => now()->subHours(30),
            'eligible_after' => now()->subHours(6),
        ]);
        $api = Mockery::mock(MaddraxikonApiClient::class);
        $api->shouldReceive('revisionDetails')
            ->once()
            ->with([$older->revision_id])
            ->andThrow(new MaddraxikonApiException('API vorübergehend gestört'));
        $api->shouldNotReceive('pageDetails');

        try {
            $this->service($api)->evaluate();
            $this->fail(
                'Der geöffnete API-Circuit muss den Queue-Retry auslösen.'
            );
        } catch (MaddraxikonApiException $exception) {
            $this->assertStringContainsString('API', $exception->getMessage());
        }

        $this->assertSame(1, $older->fresh()->evaluation_attempts);
        $this->assertSame(0, $newer->fresh()->evaluation_attempts);
        $this->assertSame(
            MaddraxikonContributionStatus::Pending,
            $newer->fresh()->status
        );
        $this->assertDatabaseCount('maddraxikon_reward_events', 0);
    }

    public function test_missing_rule_is_a_technical_error_and_keeps_source_pending(): void
    {
        [, $link] = $this->linkedMember();
        $article = $this->contribution($link, [
            'type' => MaddraxikonContributionType::New,
        ]);
        BaxxEarningRule::query()
            ->where(
                'action_key',
                MaddraxikonRewardEvent::ACTION_NEW_ARTICLE
            )
            ->delete();

        $this->assertSame(
            0,
            $this->service($this->apiForNewArticle($article))->evaluate()
        );

        $article->refresh();
        $this->assertSame(MaddraxikonContributionStatus::Pending, $article->status);
        $this->assertSame(1, $article->evaluation_attempts);
        $this->assertStringContainsString(
            'Baxx-Regel maddraxikon_new_article fehlt',
            $article->last_evaluation_error
        );
        $this->assertDatabaseCount('maddraxikon_reward_events', 0);
        $this->assertDatabaseCount('user_points', 0);
    }

    public function test_technical_failure_blocks_later_sources_for_the_same_user(): void
    {
        [, $link] = $this->linkedMember();
        $older = $this->contribution($link, [
            'type' => MaddraxikonContributionType::New,
            'occurred_at' => now()->subHours(31),
            'eligible_after' => now()->subHours(7),
        ]);
        $newer = $this->contribution($link, [
            'type' => MaddraxikonContributionType::New,
            'occurred_at' => now()->subHours(30),
            'eligible_after' => now()->subHours(6),
        ]);
        $api = Mockery::mock(MaddraxikonApiClient::class);
        $api->shouldReceive('revisionDetails')
            ->once()
            ->with([$older->revision_id, $newer->revision_id])
            ->andThrow(new MaddraxikonApiException('temporär nicht lesbar'));
        $api->shouldNotReceive('pageDetails');

        try {
            $this->service($api)->evaluate();
            $this->fail(
                'Ein technischer API-Fehler muss den Queue-Retry auslösen.'
            );
        } catch (MaddraxikonApiException $exception) {
            $this->assertStringContainsString(
                'nicht lesbar',
                $exception->getMessage()
            );
        }

        $this->assertSame(1, $older->fresh()->evaluation_attempts);
        $this->assertSame(0, $newer->fresh()->evaluation_attempts);
        $this->assertSame(
            MaddraxikonContributionStatus::Pending,
            $newer->fresh()->status
        );
        $this->assertDatabaseCount('maddraxikon_reward_events', 0);
    }

    public function test_targeted_retry_cannot_overtake_an_older_pending_source(): void
    {
        [, $link] = $this->linkedMember();
        $this->contribution($link, [
            'type' => MaddraxikonContributionType::New,
            'occurred_at' => now()->subHours(31),
            'eligible_after' => now()->subHours(7),
        ]);
        $newer = $this->contribution($link, [
            'type' => MaddraxikonContributionType::New,
            'occurred_at' => now()->subHours(30),
            'eligible_after' => now()->subHours(6),
        ]);
        $api = Mockery::mock(MaddraxikonApiClient::class);
        $api->shouldNotReceive('revisionDetails');
        $api->shouldNotReceive('pageDetails');

        $this->assertSame(
            0,
            $this->service($api)->evaluate(
                force: false,
                onlyContributionId: $newer->id
            )
        );
        $this->assertDatabaseCount('maddraxikon_reward_events', 0);
    }

    public function test_recovery_opened_during_api_validation_blocks_commit(): void
    {
        [, $link] = $this->linkedMember();
        $article = $this->contribution($link, [
            'type' => MaddraxikonContributionType::New,
        ]);
        $this->completeWatermark();
        $state = MaddraxikonSyncState::query()->sole();
        $api = Mockery::mock(MaddraxikonApiClient::class);
        $api->shouldReceive('revisionDetails')->once()->andReturn([
            $article->revision_id => $this->revisionFor($article),
        ]);
        $api->shouldReceive('pageDetails')
            ->once()
            ->andReturnUsing(function () use ($article, $state): array {
                $state->update([
                    'recovery_required_at' => now(),
                    'recovery_from_at' => now()->subDays(31),
                    'recovery_until_at' => now(),
                ]);

                return [
                    $article->page_id => [
                        'exists' => true,
                        'page_id' => $article->page_id,
                        'namespace_id' => 0,
                        'title' => $article->page_title,
                        'size' => 500,
                        'redirect' => false,
                    ],
                ];
            });

        try {
            $this->service($api)->evaluate();
            $this->fail('Recovery muss die Reward-Transaktion abbrechen.');
        } catch (RecoveryRequiredException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(
            MaddraxikonContributionStatus::Pending,
            $article->fresh()->status
        );
        $this->assertDatabaseCount('maddraxikon_reward_events', 0);
        $this->assertDatabaseCount('user_points', 0);
    }

    public function test_reversal_creates_audited_negative_booking_and_is_not_repeatable(): void
    {
        [$user, $link] = $this->linkedMember();
        $article = $this->contribution($link, [
            'type' => MaddraxikonContributionType::New,
        ]);
        $service = $this->service($this->apiForNewArticle($article));
        $service->evaluate();
        $event = MaddraxikonRewardEvent::query()->sole();
        $admin = $this->admin();

        $reversed = $service->reverse($event, $admin, 'Artikel war Plagiat');

        $this->assertSame(MaddraxikonRewardEventStatus::Reversed, $reversed->status);
        $this->assertSame($admin->id, $reversed->reversed_by);
        $this->assertSame('Artikel war Plagiat', $reversed->reversal_reason);
        $this->assertDatabaseHas('user_points', [
            'id' => $reversed->reversal_user_point_id,
            'user_id' => $user->id,
            'points' => -5,
        ]);
        $this->assertSame(0, (int) UserPoint::query()->where('user_id', $user->id)->sum('points'));

        $this->expectException(LogicException::class);
        $service->reverse($reversed, $admin, 'Noch einmal');
    }

    public function test_reversal_is_refused_when_original_booking_is_missing(): void
    {
        [, $link] = $this->linkedMember();
        $article = $this->contribution($link, [
            'type' => MaddraxikonContributionType::New,
        ]);
        $service = $this->service($this->apiForNewArticle($article));
        $service->evaluate();
        $event = MaddraxikonRewardEvent::query()->sole();
        UserPoint::query()->findOrFail($event->user_point_id)->delete();
        $event->refresh();

        try {
            $service->reverse(
                $event,
                $this->admin(),
                'Darf ohne Originalbuchung nicht ausgeführt werden'
            );
            $this->fail('A reversal without its original booking must fail.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString(
                'originale Baxx-Buchung fehlt oder ist inkonsistent',
                $exception->getMessage()
            );
        }

        $event->refresh();
        $this->assertSame(
            MaddraxikonRewardEventStatus::Awarded,
            $event->status
        );
        $this->assertNull($event->reversal_user_point_id);
        $this->assertDatabaseCount('user_points', 0);
    }

    public function test_reversal_is_refused_when_original_booking_is_inconsistent(): void
    {
        [$user, $link] = $this->linkedMember();
        $article = $this->contribution($link, [
            'type' => MaddraxikonContributionType::New,
        ]);
        $service = $this->service($this->apiForNewArticle($article));
        $service->evaluate();
        $event = MaddraxikonRewardEvent::query()->sole();
        UserPoint::query()
            ->whereKey($event->user_point_id)
            ->update(['points' => $event->awarded_points - 1]);

        try {
            $service->reverse(
                $event,
                $this->admin(),
                'Darf eine veränderte Originalbuchung nicht ausgleichen'
            );
            $this->fail('A reversal of an inconsistent booking must fail.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString(
                'originale Baxx-Buchung fehlt oder ist inkonsistent',
                $exception->getMessage()
            );
        }

        $event->refresh();
        $this->assertSame(
            MaddraxikonRewardEventStatus::Awarded,
            $event->status
        );
        $this->assertNull($event->reversal_user_point_id);
        $this->assertSame(
            4,
            (int) UserPoint::query()->where('user_id', $user->id)->sum('points')
        );
    }

    public function test_reversal_rejects_non_admin_even_when_called_outside_admin_ui(): void
    {
        [, $link] = $this->linkedMember();
        $article = $this->contribution($link, [
            'type' => MaddraxikonContributionType::New,
        ]);
        $service = $this->service($this->apiForNewArticle($article));
        $service->evaluate();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Nur Administratoren');

        $service->reverse(
            MaddraxikonRewardEvent::query()->sole(),
            User::factory()->create(),
            'Unberechtigter Versuch'
        );
    }

    public function test_reversal_requires_reason(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $api = Mockery::mock(MaddraxikonApiClient::class);
        $this->service($api)->reverse(
            new MaddraxikonRewardEvent,
            User::factory()->create(),
            '  '
        );
    }

    /**
     * @return array{User, MaddraxikonAccountLink}
     */
    private function linkedMember(): array
    {
        $user = User::factory()->create();
        Team::membersTeam()->users()->attach($user, [
            'role' => Role::Mitglied->value,
        ]);
        $link = MaddraxikonAccountLink::factory()->create([
            'user_id' => $user->id,
            'verified_at' => now()->subDays(3),
            'first_verified_at' => now()->subDays(3),
        ]);

        return [$user, $link];
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        Team::membersTeam()->users()->attach($admin, [
            'role' => Role::Admin->value,
        ]);

        return $admin;
    }

    private function contribution(
        MaddraxikonAccountLink $link,
        array $overrides = []
    ): MaddraxikonContribution {
        $externalId = $this->nextExternalId++;
        $occurredAt = now()->subHours(30);

        return MaddraxikonContribution::query()->create([
            'wiki_key' => $link->wiki_key,
            'rc_id' => $externalId,
            'revision_id' => $externalId,
            'parent_revision_id' => $externalId - 1,
            'page_id' => $externalId,
            'namespace_id' => 0,
            'page_title' => 'Testartikel '.$externalId,
            'wiki_user_id' => $link->wiki_user_id,
            'wiki_username' => $link->wiki_username,
            'account_link_id' => $link->id,
            'user_id' => $link->user_id,
            'type' => MaddraxikonContributionType::Edit,
            'minor' => false,
            'bot' => false,
            'anonymous' => false,
            'redirect' => false,
            'user_hidden' => false,
            'old_size' => 600,
            'new_size' => 650,
            'tags' => [],
            'occurred_at' => $occurredAt,
            'session_anchor_revision_id' => $externalId,
            'status' => MaddraxikonContributionStatus::Pending,
            'eligible_after' => $occurredAt->copy()->addDay(),
            ...$overrides,
        ]);
    }

    private function completeWatermark(): void
    {
        MaddraxikonSyncState::query()->updateOrCreate(
            ['wiki_key' => config('maddraxikon.wiki_key')],
            ['watermark_at' => now()]
        );
    }

    private function service(MaddraxikonApiClient $api): MaddraxikonRewardService
    {
        return new MaddraxikonRewardService($api);
    }

    private function apiWithValidRevisions($contributions): MaddraxikonApiClient
    {
        $byRevision = $contributions->keyBy('revision_id');
        $api = Mockery::mock(MaddraxikonApiClient::class);
        $api->shouldReceive('revisionDetails')
            ->andReturnUsing(function (array $revisionIds) use ($byRevision): array {
                return collect($revisionIds)
                    ->mapWithKeys(function (int $revisionId) use ($byRevision): array {
                        $contribution = $byRevision->get($revisionId);

                        return [$revisionId => $this->revisionFor($contribution)];
                    })
                    ->all();
            });

        return $api;
    }

    private function apiForNewArticle(
        MaddraxikonContribution $article,
        array $revisionOverrides = [],
        array $pageOverrides = []
    ): MaddraxikonApiClient {
        $api = Mockery::mock(MaddraxikonApiClient::class);
        $api->shouldReceive('revisionDetails')->once()->andReturn([
            $article->revision_id => $this->revisionFor($article, $revisionOverrides),
        ]);

        $revisionFailsBeforePageCheck =
            ($revisionOverrides['tags'] ?? []) === ['mw-reverted']
            || ($revisionOverrides['user_hidden'] ?? false)
            || ($revisionOverrides['suppressed'] ?? false)
            || ($revisionOverrides['sha1_hidden'] ?? false)
            || ($revisionOverrides['text_hidden'] ?? false);

        if (! $revisionFailsBeforePageCheck) {
            $api->shouldReceive('pageDetails')->once()->andReturn([
                $article->page_id => [
                    'exists' => true,
                    'page_id' => $article->page_id,
                    'namespace_id' => 0,
                    'title' => $article->page_title,
                    'size' => 500,
                    'redirect' => false,
                    ...$pageOverrides,
                ],
            ]);
        }

        return $api;
    }

    private function revisionFor(
        MaddraxikonContribution $contribution,
        array $overrides = []
    ): array {
        return [
            'exists' => true,
            'revision_id' => $contribution->revision_id,
            'page_id' => $contribution->page_id,
            'namespace_id' => $contribution->namespace_id,
            'user_id' => $contribution->wiki_user_id,
            'user_hidden' => false,
            'suppressed' => false,
            'size' => $contribution->new_size,
            'tags' => [],
            ...$overrides,
        ];
    }
}
