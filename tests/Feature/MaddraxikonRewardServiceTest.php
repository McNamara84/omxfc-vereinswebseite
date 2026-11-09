<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MaddraxikonAccountLinkStatus;
use App\Enums\MaddraxikonContributionStatus;
use App\Enums\MaddraxikonContributionType;
use App\Enums\MaddraxikonRewardEventStatus;
use App\Enums\Role;
use App\Exceptions\MaddraxikonApiException;
use App\Models\Activity;
use App\Models\BaxxEarningRule;
use App\Models\MaddraxikonRewardEvent;
use App\Models\MaddraxikonSyncState;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPoint;
use App\Services\Maddraxikon\Exceptions\RecoveryRequiredException;
use App\Services\Maddraxikon\MaddraxikonApiClient;
use App\Services\Maddraxikon\MaddraxikonRewardService;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use LogicException;
use Mockery;
use RuntimeException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(MaddraxikonRewardServiceTestFixtures::class);
covers(MaddraxikonRewardService::class);
dataset('MaddraxikonRewardServiceTest.invalidArticleProvider', static fn (): array => ['revision reverted' => [['tags' => ['mw-reverted']], [], 'revision_reverted'], 'page deleted' => [[], ['exists' => false], 'page_missing'], 'redirect' => [[], ['redirect' => true], 'page_is_redirect'], 'too short' => [[], ['size' => 499], 'article_too_short'], 'wrong namespace' => [[], ['namespace_id' => 10], 'not_an_article'], 'SHA-1 hidden by revision deletion' => [['sha1_hidden' => true], [], 'revision_hidden'], 'text hidden by revision deletion' => [['text_hidden' => true], [], 'revision_hidden'], 'author suppressed' => [['user_hidden' => true, 'suppressed' => true], [], 'revision_hidden']]);
beforeEach(function (): void {
    config(['maddraxikon.features.awards_enabled' => true, 'maddraxikon.features.sync_enabled' => true, 'maddraxikon.daily_point_cap' => 10, 'maddraxikon.minimum_article_bytes' => 500, 'maddraxikon.article_namespace' => 0, 'maddraxikon.session_window_minutes' => 30, 'maddraxikon.timezone' => 'Europe/Berlin', 'maddraxikon.evaluation.source_batch_size' => 100, 'maddraxikon.evaluation.api_batch_size' => 50]);
    BaxxEarningRule::query()->updateOrCreate(['action_key' => MaddraxikonRewardEvent::ACTION_EDIT_SESSION], ['label' => 'Maddraxikon-Bearbeitungen', 'description' => 'Eine Baxx je fünf Bearbeitungssitzungen.', 'points' => 1, 'every_count' => 5, 'is_active' => true]);
    BaxxEarningRule::query()->updateOrCreate(['action_key' => MaddraxikonRewardEvent::ACTION_NEW_ARTICLE], ['label' => 'Maddraxikon-Artikel', 'description' => 'Fünf Baxx je neuem qualifiziertem Artikel.', 'points' => 5, 'every_count' => 1, 'is_active' => true]);
});
test('feature flag prevents evaluation without api calls', function (): void {
    config(['maddraxikon.features.awards_enabled' => false]);
    [, $link] = $this->linkedMember();
    $this->contribution($link, ['type' => MaddraxikonContributionType::New]);
    $api = Mockery::mock(MaddraxikonApiClient::class);
    $api->shouldNotReceive('revisionDetails');
    $api->shouldNotReceive('pageDetails');
    expect($this->service($api)->evaluate())->toBe(0);
    $this->assertDatabaseCount('maddraxikon_reward_events', 0);
});
test('evaluation fails closed when awards are enabled without import', function (): void {
    config(['maddraxikon.features.sync_enabled' => false]);
    [, $link] = $this->linkedMember();
    $this->contribution($link, ['type' => MaddraxikonContributionType::New]);
    $api = Mockery::mock(MaddraxikonApiClient::class);
    $api->shouldNotReceive('revisionDetails');
    $api->shouldNotReceive('pageDetails');
    try {
        $this->service($api)->evaluate();
        $this->fail('Auszahlungen ohne aktiven Import müssen gesperrt sein.');
    } catch (LogicException $exception) {
        expect($exception->getMessage())->toContain('aktiven Import');
    }
    $this->assertDatabaseCount('maddraxikon_reward_events', 0);
});
test('open recovery window blocks all evaluation even when forced', function (): void {
    [, $link] = $this->linkedMember();
    $this->contribution($link, ['type' => MaddraxikonContributionType::New]);
    MaddraxikonSyncState::factory()->create(['recovery_required_at' => now(), 'recovery_from_at' => now()->subDays(31), 'recovery_until_at' => now()]);
    $api = Mockery::mock(MaddraxikonApiClient::class);
    $api->shouldNotReceive('revisionDetails');
    $api->shouldNotReceive('pageDetails');
    expect(fn () => $this->service($api)->evaluate(force: true))->toThrow(LogicException::class, 'offenen Recovery-Fensters gesperrt');
});
test('every fifth edit session awards one baxx once', function (): void {
    [$user, $link] = $this->linkedMember();
    $contributions = collect();
    foreach (range(1, 5) as $index) {
        $contributions->push($this->contribution($link, ['page_id' => 20000 + $index, 'page_title' => 'Artikel '.$index, 'occurred_at' => now()->subDays(2)->startOfDay()->addHour()->addMinutes($index)]));
    }
    $this->completeWatermark();
    $api = $this->apiWithValidRevisions($contributions);
    $service = $this->service($api);
    expect($service->evaluate())->toBe(5)
        ->and($service->evaluate())->toBe(0);
    $this->assertDatabaseCount('maddraxikon_reward_events', 5);
    $this->assertDatabaseHas('maddraxikon_reward_events', ['user_id' => $user->id, 'sequence_number' => 5, 'candidate_points' => 1, 'awarded_points' => 1, 'status' => MaddraxikonRewardEventStatus::Awarded->value]);
    $this->assertDatabaseHas('user_points', ['user_id' => $user->id, 'team_id' => Team::membersTeam()->id, 'points' => 1]);
});
test('edit session counts once and rejects only invalid revisions', function (): void {
    [, $link] = $this->linkedMember();
    BaxxEarningRule::query()->where('action_key', MaddraxikonRewardEvent::ACTION_EDIT_SESSION)->update(['every_count' => 1]);
    $first = $this->contribution($link, ['page_id' => 123, 'occurred_at' => now()->subDays(2)->startOfDay()->addHour()]);
    $second = $this->contribution($link, ['page_id' => 123, 'occurred_at' => now()->subDays(2)->startOfDay()->addHour()->addMinutes(30), 'session_anchor_revision_id' => $first->revision_id]);
    $first->update(['session_anchor_revision_id' => $first->revision_id]);
    $this->completeWatermark();
    $api = Mockery::mock(MaddraxikonApiClient::class);
    $api->shouldReceive('revisionDetails')->once()->andReturn([$first->revision_id => $this->revisionFor($first, ['tags' => ['mw-reverted']]), $second->revision_id => $this->revisionFor($second)]);
    expect($this->service($api)->evaluate())->toBe(1);
    $this->assertDatabaseCount('maddraxikon_reward_events', 1);
    $this->assertDatabaseHas('maddraxikon_contributions', ['id' => $first->id, 'status' => MaddraxikonContributionStatus::Rejected->value, 'status_reason' => 'revision_reverted']);
    $this->assertDatabaseHas('maddraxikon_contributions', ['id' => $second->id, 'status' => MaddraxikonContributionStatus::Awarded->value]);
});
test('all reverted session revisions are rejected without sequence', function (): void {
    [, $link] = $this->linkedMember();
    $contribution = $this->contribution($link);
    $contribution->update(['session_anchor_revision_id' => $contribution->revision_id]);
    $this->completeWatermark();
    $api = Mockery::mock(MaddraxikonApiClient::class);
    $api->shouldReceive('revisionDetails')->once()->andReturn([$contribution->revision_id => $this->revisionFor($contribution, ['tags' => ['mw-reverted']])]);
    expect($this->service($api)->evaluate())->toBe(1);
    $event = MaddraxikonRewardEvent::query()->sole();
    expect($event->status)->toBe(MaddraxikonRewardEventStatus::Rejected)
        ->and($event->sequence_number)->toBeNull()
        ->and($event->status_reason)->toBe('revision_reverted');
});
test('qualified new article awards five baxx', function (): void {
    [$user, $link] = $this->linkedMember();
    $article = $this->contribution($link, ['type' => MaddraxikonContributionType::New, 'parent_revision_id' => 0]);
    $api = $this->apiForNewArticle($article);
    expect($this->service($api)->evaluate())->toBe(1);
    $this->assertDatabaseHas('maddraxikon_reward_events', ['source_key' => 'new:'.$article->revision_id, 'candidate_points' => 5, 'awarded_points' => 5, 'status' => MaddraxikonRewardEventStatus::Awarded->value]);
    $this->assertDatabaseHas('user_points', ['user_id' => $user->id, 'points' => 5]);
    $this->assertDatabaseHas('activities', ['user_id' => $user->id, 'subject_type' => User::class, 'subject_id' => $user->id, 'action' => Activity::ACTION_MADDRAXIKON_BAXX_AWARDED_PREFIX.'5']);
    $this->assertDatabaseHas('maddraxikon_reward_events', ['source_key' => 'new:'.$article->revision_id, 'activity_pending' => false]);
});
test('failed activity insert is reconciled once on retry', function (): void {
    [$user, $link] = $this->linkedMember();
    $article = $this->contribution($link, ['type' => MaddraxikonContributionType::New, 'parent_revision_id' => 0]);
    $service = $this->service($this->apiForNewArticle($article));
    $failActivityInsert = true;
    Activity::creating(static function (Activity $activity) use (&$failActivityInsert): void {
        if ($failActivityInsert && str_starts_with((string) $activity->action, Activity::ACTION_MADDRAXIKON_BAXX_AWARDED_PREFIX)) {
            throw new RuntimeException('Simulierter Activity-Insert-Fehler.');
        }
    });
    try {
        $service->evaluate();
        $this->fail('Der simulierte Activity-Fehler wurde nicht ausgelöst.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Simulierter Activity-Insert-Fehler.');
    } finally {
        $failActivityInsert = false;
        Activity::flushEventListeners();
    }
    expect(Activity::getEventDispatcher()->getListeners('eloquent.creating: '.Activity::class))->toHaveCount(0);
    $this->assertDatabaseHas('maddraxikon_reward_events', ['source_key' => 'new:'.$article->revision_id, 'awarded_points' => 5, 'activity_pending' => true]);
    $this->assertDatabaseHas('user_points', ['user_id' => $user->id, 'points' => 5]);
    $this->assertDatabaseMissing('activities', ['user_id' => $user->id, 'action' => Activity::ACTION_MADDRAXIKON_BAXX_AWARDED_PREFIX.'5']);
    expect($service->evaluate())->toBe(0)
        ->and($service->evaluate())->toBe(0);
    $this->assertDatabaseHas('maddraxikon_reward_events', ['source_key' => 'new:'.$article->revision_id, 'activity_pending' => false]);
    expect(Activity::query()->where('user_id', $user->id)->where('action', Activity::ACTION_MADDRAXIKON_BAXX_AWARDED_PREFIX.'5')->count())->toBe(1);
});
test('historical reward events are not announced retroactively', function (): void {
    [$user, $link] = $this->linkedMember();
    MaddraxikonRewardEvent::factory()->create(['user_id' => $user->id, 'account_link_id' => $link->id, 'awarded_points' => 5, 'candidate_points' => 5, 'status' => MaddraxikonRewardEventStatus::Awarded, 'awarded_at' => now()->subDay(), 'activity_pending' => false]);
    $api = Mockery::mock(MaddraxikonApiClient::class);
    $api->shouldNotReceive('revisionDetails');
    $api->shouldNotReceive('pageDetails');
    expect($this->service($api)->evaluate())->toBe(0)
        ->and(Activity::query()->where('action', 'like', Activity::ACTION_MADDRAXIKON_BAXX_AWARDED_PREFIX.'%')->exists())->toBeFalse();
});
test('invalid new article is rejected', function (array $revisionOverrides, array $pageOverrides, string $expectedReason): void {
    [, $link] = $this->linkedMember();
    $article = $this->contribution($link, ['type' => MaddraxikonContributionType::New, 'parent_revision_id' => 0]);
    $api = $this->apiForNewArticle($article, $revisionOverrides, $pageOverrides);
    expect($this->service($api)->evaluate())->toBe(1);
    $this->assertDatabaseHas('maddraxikon_reward_events', ['source_key' => 'new:'.$article->revision_id, 'status' => MaddraxikonRewardEventStatus::Rejected->value, 'status_reason' => $expectedReason, 'awarded_points' => 0]);
    $this->assertDatabaseCount('user_points', 0);
    expect(Activity::query()->where('action', 'like', Activity::ACTION_MADDRAXIKON_BAXX_AWARDED_PREFIX.'%')->exists())->toBeFalse();
})->with('MaddraxikonRewardServiceTest.invalidArticleProvider');
test('new article must have originated in the article namespace', function (): void {
    [, $link] = $this->linkedMember();
    $article = $this->contribution($link, ['type' => MaddraxikonContributionType::New, 'parent_revision_id' => 0, 'namespace_id' => 10]);
    $api = Mockery::mock(MaddraxikonApiClient::class);
    $api->shouldNotReceive('revisionDetails');
    $api->shouldNotReceive('pageDetails');
    expect($this->service($api)->evaluate())->toBe(1);
    $this->assertDatabaseHas('maddraxikon_reward_events', ['source_key' => 'new:'.$article->revision_id, 'status' => MaddraxikonRewardEventStatus::Rejected->value, 'status_reason' => 'source_namespace_not_article', 'awarded_points' => 0]);
    $this->assertDatabaseHas('maddraxikon_contributions', ['id' => $article->id, 'status' => MaddraxikonContributionStatus::Rejected->value]);
});
test('daily cap is partially applied and overflow expires', function (): void {
    [$user, $link] = $this->linkedMember();
    $first = $this->contribution($link, ['type' => MaddraxikonContributionType::New, 'occurred_at' => now()->subHours(30)]);
    $activityDate = $first->occurred_at->timezone('Europe/Berlin')->toDateString();
    MaddraxikonRewardEvent::query()->create(['wiki_key' => $first->wiki_key, 'user_id' => $user->id, 'account_link_id' => $link->id, 'source_contribution_id' => null, 'action_key' => MaddraxikonRewardEvent::ACTION_NEW_ARTICLE, 'source_key' => 'new:prior', 'source_revision_id' => 9999, 'activity_date' => $activityDate, 'sequence_number' => 1, 'rule_points' => 8, 'rule_every_count' => 1, 'candidate_points' => 8, 'awarded_points' => 8, 'capped_points' => 0, 'status' => MaddraxikonRewardEventStatus::Awarded, 'awarded_at' => now()]);
    $api = $this->apiForNewArticle($first);
    expect($this->service($api)->evaluate())->toBe(1);
    $this->assertDatabaseHas('maddraxikon_reward_events', ['source_key' => 'new:'.$first->revision_id, 'candidate_points' => 5, 'awarded_points' => 2, 'capped_points' => 3, 'status_reason' => 'daily_cap_partially_applied']);
    $nextDay = $this->contribution($link, ['type' => MaddraxikonContributionType::New, 'occurred_at' => $first->occurred_at->copy()->addDay()]);
    $api = $this->apiForNewArticle($nextDay);
    expect($this->service($api)->evaluate())->toBe(1);
    $this->assertDatabaseHas('maddraxikon_reward_events', ['source_key' => 'new:'.$nextDay->revision_id, 'awarded_points' => 5, 'capped_points' => 0]);
});
test('reversed award does not restore daily cap', function (): void {
    [$user, $link] = $this->linkedMember();
    $article = $this->contribution($link, ['type' => MaddraxikonContributionType::New]);
    $service = $this->service($this->apiForNewArticle($article));
    $service->evaluate();
    $admin = $this->admin();
    $service->reverse(MaddraxikonRewardEvent::query()->sole(), $admin, 'Missbrauch bestätigt');
    $next = $this->contribution($link, ['type' => MaddraxikonContributionType::New, 'occurred_at' => $article->occurred_at->copy()->addMinute()]);
    $this->service($this->apiForNewArticle($next))->evaluate();
    expect((int) MaddraxikonRewardEvent::query()->where('user_id', $user->id)->sum('awarded_points'))->toBe(10)
        ->and((int) UserPoint::query()->where('user_id', $user->id)->sum('points'))->toBe(5);
});
test('disconnected link before evaluation is rejected without api', function (): void {
    [, $link] = $this->linkedMember();
    $article = $this->contribution($link, ['type' => MaddraxikonContributionType::New]);
    $link->update(['status' => MaddraxikonAccountLinkStatus::Disconnected, 'disconnected_at' => now()]);
    $api = Mockery::mock(MaddraxikonApiClient::class);
    $api->shouldNotReceive('revisionDetails');
    $api->shouldNotReceive('pageDetails');
    expect($this->service($api)->evaluate())->toBe(1);
    $this->assertDatabaseHas('maddraxikon_contributions', ['id' => $article->id, 'status' => MaddraxikonContributionStatus::Rejected->value, 'status_reason' => 'account_link_inactive']);
});
test('applicant is not eligible for award', function (): void {
    [$user, $link] = $this->linkedMember();
    Team::membersTeam()->users()->updateExistingPivot($user->id, ['role' => Role::Anwaerter->value]);
    $article = $this->contribution($link, ['type' => MaddraxikonContributionType::New]);
    $api = Mockery::mock(MaddraxikonApiClient::class);
    $api->shouldNotReceive('revisionDetails');
    expect($this->service($api)->evaluate())->toBe(1);
    $this->assertDatabaseHas('maddraxikon_reward_events', ['source_key' => 'new:'.$article->revision_id, 'status_reason' => 'membership_inactive']);
});
test('future due date and incomplete session watermark remain pending', function (): void {
    [, $link] = $this->linkedMember();
    $future = $this->contribution($link, ['type' => MaddraxikonContributionType::New, 'eligible_after' => now()->addMinute()]);
    $edit = $this->contribution($link, ['occurred_at' => now()->subHours(30)]);
    $edit->update(['session_anchor_revision_id' => $edit->revision_id]);
    MaddraxikonSyncState::query()->create(['wiki_key' => $edit->wiki_key, 'watermark_at' => $edit->occurred_at->copy()->addMinutes(29)]);
    $api = Mockery::mock(MaddraxikonApiClient::class);
    $api->shouldNotReceive('revisionDetails');
    $api->shouldNotReceive('pageDetails');
    expect($this->service($api)->evaluate())->toBe(0)
        ->and($future->fresh()->status)->toBe(MaddraxikonContributionStatus::Pending)
        ->and($edit->fresh()->status)->toBe(MaddraxikonContributionStatus::Pending);
});
test('inactive rule consumes sequence without award', function (): void {
    [$user, $link] = $this->linkedMember();
    $rule = BaxxEarningRule::query()->where('action_key', MaddraxikonRewardEvent::ACTION_EDIT_SESSION)->firstOrFail();
    $rule->update(['every_count' => 2, 'is_active' => false]);
    $first = $this->contribution($link, ['page_id' => 100]);
    $first->update(['session_anchor_revision_id' => $first->revision_id]);
    $this->completeWatermark();
    $this->service($this->apiWithValidRevisions(collect([$first])))->evaluate();
    $this->assertDatabaseHas('maddraxikon_reward_events', ['source_key' => 'edit-session:'.$first->revision_id, 'sequence_number' => 1, 'awarded_points' => 0, 'status_reason' => 'rule_inactive']);
    $rule->update(['is_active' => true]);
    $second = $this->contribution($link, ['page_id' => 101]);
    $second->update(['session_anchor_revision_id' => $second->revision_id]);
    $this->completeWatermark();
    $this->service($this->apiWithValidRevisions(collect([$second])))->evaluate();
    $this->assertDatabaseHas('maddraxikon_reward_events', ['source_key' => 'edit-session:'.$second->revision_id, 'sequence_number' => 2, 'awarded_points' => 1]);
    expect((int) UserPoint::query()->where('user_id', $user->id)->sum('points'))->toBe(1);
});
test('edit sessions are sequenced by last activity then revision', function (): void {
    [, $link] = $this->linkedMember();
    $start = now()->subDays(2)->startOfDay()->addHour();
    $longSession = $this->contribution($link, ['page_id' => 900, 'occurred_at' => $start]);
    $longSession->update(['session_anchor_revision_id' => $longSession->revision_id]);
    $longSessionMiddle = $this->contribution($link, ['page_id' => 900, 'occurred_at' => $start->copy()->addMinutes(25), 'session_anchor_revision_id' => $longSession->revision_id]);
    $longSessionLast = $this->contribution($link, ['page_id' => 900, 'occurred_at' => $start->copy()->addMinutes(50), 'session_anchor_revision_id' => $longSession->revision_id]);
    $shortSessions = collect();
    foreach ([10, 20, 30, 40] as $index => $minutes) {
        $short = $this->contribution($link, ['page_id' => 910 + $index, 'occurred_at' => $start->copy()->addMinutes($minutes)]);
        $short->update(['session_anchor_revision_id' => $short->revision_id]);
        $shortSessions->push($short);
    }
    $this->completeWatermark();
    $allContributions = collect([$longSession, $longSessionMiddle, $longSessionLast, ...$shortSessions->all()]);
    $this->service($this->apiWithValidRevisions($allContributions))->evaluate();
    $this->assertDatabaseHas('maddraxikon_reward_events', ['source_key' => 'edit-session:'.$longSession->revision_id, 'sequence_number' => 5, 'awarded_points' => 1]);
    $this->assertDatabaseHas('maddraxikon_reward_events', ['source_key' => 'edit-session:'.$shortSessions->last()->revision_id, 'sequence_number' => 4, 'awarded_points' => 0]);
});
test('activity date uses berlin timezone', function (): void {
    $activityAt = CarbonImmutable::parse('2026-07-18 23:30:00', 'UTC');
    $this->travelTo($activityAt->addDay()->addMinute());
    [, $link] = $this->linkedMember();
    $article = $this->contribution($link, ['type' => MaddraxikonContributionType::New, 'occurred_at' => $activityAt->setTimezone(config('app.timezone')), 'eligible_after' => $activityAt->addDay()->setTimezone(config('app.timezone'))]);
    $this->service($this->apiForNewArticle($article))->evaluate();
    expect(MaddraxikonRewardEvent::query()->where('source_key', 'new:'.$article->revision_id)->sole()->activity_date->toDateString())->toBe('2026-07-19');
});
test('api failure leaves contribution pending and writes no ledger', function (): void {
    [, $link] = $this->linkedMember();
    $article = $this->contribution($link, ['type' => MaddraxikonContributionType::New]);
    $api = Mockery::mock(MaddraxikonApiClient::class);
    $api->shouldReceive('revisionDetails')->once()->andThrow(new MaddraxikonApiException('nicht erreichbar'));
    try {
        $this->service($api)->evaluate();
        $this->fail('Ein systemischer API-Fehler muss den Queue-Retry auslösen.');
    } catch (MaddraxikonApiException $exception) {
        expect($exception->getMessage())->toBe('nicht erreichbar');
    }
    $article->refresh();
    expect($article->status)->toBe(MaddraxikonContributionStatus::Pending)
        ->and($article->evaluation_attempts)->toBe(1)
        ->and($article->last_evaluation_error)->toContain('MaddraxikonApiException: nicht erreichbar')
        ->and($article->last_evaluation_error_at)->not->toBeNull();
    $this->assertDatabaseCount('maddraxikon_reward_events', 0);
});
test('evaluation run limits sources without splitting edit sessions', function (): void {
    config(['maddraxikon.evaluation.source_batch_size' => 2]);
    [, $link] = $this->linkedMember();
    $startedAt = now()->subDays(2)->startOfDay()->addHour();
    $first = $this->contribution($link, ['page_id' => 501, 'occurred_at' => $startedAt]);
    $firstFollowUp = $this->contribution($link, ['page_id' => 501, 'occurred_at' => $startedAt->copy()->addMinutes(10), 'session_anchor_revision_id' => $first->revision_id]);
    $second = $this->contribution($link, ['page_id' => 502, 'occurred_at' => $startedAt->copy()->addMinutes(20)]);
    $third = $this->contribution($link, ['page_id' => 503, 'occurred_at' => $startedAt->copy()->addMinutes(30)]);
    $this->completeWatermark();
    $api = Mockery::mock(MaddraxikonApiClient::class);
    $api->shouldReceive('revisionDetails')->once()->with([$first->revision_id, $firstFollowUp->revision_id, $second->revision_id])->andReturn([$first->revision_id => $this->revisionFor($first), $firstFollowUp->revision_id => $this->revisionFor($firstFollowUp), $second->revision_id => $this->revisionFor($second)]);
    $api->shouldNotReceive('pageDetails');
    expect($this->service($api)->evaluate())->toBe(2);
    $this->assertDatabaseCount('maddraxikon_reward_events', 2);
    expect($first->fresh()->status)->not->toBe(MaddraxikonContributionStatus::Pending)
        ->and($firstFollowUp->fresh()->status)->not->toBe(MaddraxikonContributionStatus::Pending)
        ->and($second->fresh()->status)->not->toBe(MaddraxikonContributionStatus::Pending)
        ->and($third->fresh()->status)->toBe(MaddraxikonContributionStatus::Pending);
});
test('remote validation is bundled into configured chunks', function (): void {
    config(['maddraxikon.evaluation.api_batch_size' => 2]);
    [$user, $link] = $this->linkedMember();
    $articles = collect();
    $startedAt = now()->subDays(2)->startOfDay()->addHour();
    foreach (range(1, 5) as $index) {
        $articles->push($this->contribution($link, ['type' => MaddraxikonContributionType::New, 'parent_revision_id' => 0, 'occurred_at' => $startedAt->copy()->addMinutes($index)]));
    }
    $byRevision = $articles->keyBy('revision_id');
    $byPage = $articles->keyBy('page_id');
    $revisionBatches = [];
    $pageBatches = [];
    $api = Mockery::mock(MaddraxikonApiClient::class);
    $api->shouldReceive('revisionDetails')->times(3)->andReturnUsing(function (array $ids) use (&$revisionBatches, $byRevision): array {
        $revisionBatches[] = $ids;

        return collect($ids)->mapWithKeys(fn (int $id): array => [$id => $this->revisionFor($byRevision->get($id))])->all();
    });
    $api->shouldReceive('pageDetails')->times(3)->andReturnUsing(function (array $ids) use (&$pageBatches, $byPage): array {
        $pageBatches[] = $ids;

        return collect($ids)->mapWithKeys(function (int $id) use ($byPage): array {
            $article = $byPage->get($id);

            return [$id => ['exists' => true, 'page_id' => $id, 'namespace_id' => 0, 'title' => $article->page_title, 'size' => 500, 'redirect' => false]];
        })->all();
    });
    expect($this->service($api)->evaluate())->toBe(5)
        ->and($revisionBatches)->toBe(array_chunk($articles->pluck('revision_id')->all(), 2))
        ->and($pageBatches)->toBe(array_chunk($articles->pluck('page_id')->all(), 2));
    $this->assertDatabaseCount('maddraxikon_reward_events', 5);
    $this->assertDatabaseHas('activities', ['user_id' => $user->id, 'subject_type' => User::class, 'subject_id' => $user->id, 'action' => Activity::ACTION_MADDRAXIKON_BAXX_AWARDED_PREFIX.'10']);
    expect(Activity::query()->where('user_id', $user->id)->where('action', 'like', Activity::ACTION_MADDRAXIKON_BAXX_AWARDED_PREFIX.'%')->count())->toBe(1);
});
test('api failure opens circuit for other users in same run', function (): void {
    config(['maddraxikon.evaluation.api_batch_size' => 1]);
    [, $firstLink] = $this->linkedMember();
    [, $secondLink] = $this->linkedMember();
    $older = $this->contribution($firstLink, ['type' => MaddraxikonContributionType::New, 'occurred_at' => now()->subHours(31), 'eligible_after' => now()->subHours(7)]);
    $newer = $this->contribution($secondLink, ['type' => MaddraxikonContributionType::New, 'occurred_at' => now()->subHours(30), 'eligible_after' => now()->subHours(6)]);
    $api = Mockery::mock(MaddraxikonApiClient::class);
    $api->shouldReceive('revisionDetails')->once()->with([$older->revision_id])->andThrow(new MaddraxikonApiException('API vorübergehend gestört'));
    $api->shouldNotReceive('pageDetails');
    try {
        $this->service($api)->evaluate();
        $this->fail('Der geöffnete API-Circuit muss den Queue-Retry auslösen.');
    } catch (MaddraxikonApiException $exception) {
        expect($exception->getMessage())->toContain('API');
    }
    expect($older->fresh()->evaluation_attempts)->toBe(1)
        ->and($newer->fresh()->evaluation_attempts)->toBe(0)
        ->and($newer->fresh()->status)->toBe(MaddraxikonContributionStatus::Pending);
    $this->assertDatabaseCount('maddraxikon_reward_events', 0);
});
test('missing rule is a technical error and keeps source pending', function (): void {
    [, $link] = $this->linkedMember();
    $article = $this->contribution($link, ['type' => MaddraxikonContributionType::New]);
    BaxxEarningRule::query()->where('action_key', MaddraxikonRewardEvent::ACTION_NEW_ARTICLE)->delete();
    expect($this->service($this->apiForNewArticle($article))->evaluate())->toBe(0);
    $article->refresh();
    expect($article->status)->toBe(MaddraxikonContributionStatus::Pending)
        ->and($article->evaluation_attempts)->toBe(1)
        ->and($article->last_evaluation_error)->toContain('Baxx-Regel maddraxikon_new_article fehlt');
    $this->assertDatabaseCount('maddraxikon_reward_events', 0);
    $this->assertDatabaseCount('user_points', 0);
});
test('technical failure blocks later sources for the same user', function (): void {
    [, $link] = $this->linkedMember();
    $older = $this->contribution($link, ['type' => MaddraxikonContributionType::New, 'occurred_at' => now()->subHours(31), 'eligible_after' => now()->subHours(7)]);
    $newer = $this->contribution($link, ['type' => MaddraxikonContributionType::New, 'occurred_at' => now()->subHours(30), 'eligible_after' => now()->subHours(6)]);
    $api = Mockery::mock(MaddraxikonApiClient::class);
    $api->shouldReceive('revisionDetails')->once()->with([$older->revision_id, $newer->revision_id])->andThrow(new MaddraxikonApiException('temporär nicht lesbar'));
    $api->shouldNotReceive('pageDetails');
    try {
        $this->service($api)->evaluate();
        $this->fail('Ein technischer API-Fehler muss den Queue-Retry auslösen.');
    } catch (MaddraxikonApiException $exception) {
        expect($exception->getMessage())->toContain('nicht lesbar');
    }
    expect($older->fresh()->evaluation_attempts)->toBe(1)
        ->and($newer->fresh()->evaluation_attempts)->toBe(0)
        ->and($newer->fresh()->status)->toBe(MaddraxikonContributionStatus::Pending);
    $this->assertDatabaseCount('maddraxikon_reward_events', 0);
});
test('targeted retry cannot overtake an older pending source', function (): void {
    [, $link] = $this->linkedMember();
    $this->contribution($link, ['type' => MaddraxikonContributionType::New, 'occurred_at' => now()->subHours(31), 'eligible_after' => now()->subHours(7)]);
    $newer = $this->contribution($link, ['type' => MaddraxikonContributionType::New, 'occurred_at' => now()->subHours(30), 'eligible_after' => now()->subHours(6)]);
    $api = Mockery::mock(MaddraxikonApiClient::class);
    $api->shouldNotReceive('revisionDetails');
    $api->shouldNotReceive('pageDetails');
    expect($this->service($api)->evaluate(force: false, onlyContributionId: $newer->id))->toBe(0);
    $this->assertDatabaseCount('maddraxikon_reward_events', 0);
});
test('recovery opened during api validation blocks commit', function (): void {
    [, $link] = $this->linkedMember();
    $article = $this->contribution($link, ['type' => MaddraxikonContributionType::New]);
    $this->completeWatermark();
    $state = MaddraxikonSyncState::query()->sole();
    $api = Mockery::mock(MaddraxikonApiClient::class);
    $api->shouldReceive('revisionDetails')->once()->andReturn([$article->revision_id => $this->revisionFor($article)]);
    $api->shouldReceive('pageDetails')->once()->andReturnUsing(function () use ($article, $state): array {
        $state->update(['recovery_required_at' => now(), 'recovery_from_at' => now()->subDays(31), 'recovery_until_at' => now()]);

        return [$article->page_id => ['exists' => true, 'page_id' => $article->page_id, 'namespace_id' => 0, 'title' => $article->page_title, 'size' => 500, 'redirect' => false]];
    });
    try {
        $this->service($api)->evaluate();
        $this->fail('Recovery muss die Reward-Transaktion abbrechen.');
    } catch (RecoveryRequiredException) {
        $this->addToAssertionCount(1);
    }
    expect($article->fresh()->status)->toBe(MaddraxikonContributionStatus::Pending);
    $this->assertDatabaseCount('maddraxikon_reward_events', 0);
    $this->assertDatabaseCount('user_points', 0);
});
test('reversal creates audited negative booking and is not repeatable', function (): void {
    [$user, $link] = $this->linkedMember();
    $article = $this->contribution($link, ['type' => MaddraxikonContributionType::New]);
    $service = $this->service($this->apiForNewArticle($article));
    $service->evaluate();
    $event = MaddraxikonRewardEvent::query()->sole();
    $admin = $this->admin();
    $reversed = $service->reverse($event, $admin, 'Artikel war Plagiat');
    expect($reversed->status)->toBe(MaddraxikonRewardEventStatus::Reversed)
        ->and($reversed->reversed_by)->toBe($admin->id)
        ->and($reversed->reversal_reason)->toBe('Artikel war Plagiat');
    $this->assertDatabaseHas('user_points', ['id' => $reversed->reversal_user_point_id, 'user_id' => $user->id, 'points' => -5]);
    expect((int) UserPoint::query()->where('user_id', $user->id)->sum('points'))->toBe(0)
        ->and(fn () => $service->reverse($reversed, $admin, 'Noch einmal'))->toThrow(LogicException::class);
});
test('reversal is refused when original booking is missing', function (): void {
    [, $link] = $this->linkedMember();
    $article = $this->contribution($link, ['type' => MaddraxikonContributionType::New]);
    $service = $this->service($this->apiForNewArticle($article));
    $service->evaluate();
    $event = MaddraxikonRewardEvent::query()->sole();
    UserPoint::query()->findOrFail($event->user_point_id)->delete();
    $event->refresh();
    try {
        $service->reverse($event, $this->admin(), 'Darf ohne Originalbuchung nicht ausgeführt werden');
        $this->fail('A reversal without its original booking must fail.');
    } catch (LogicException $exception) {
        expect($exception->getMessage())->toContain('originale Baxx-Buchung fehlt oder ist inkonsistent');
    }
    $event->refresh();
    expect($event->status)->toBe(MaddraxikonRewardEventStatus::Awarded)
        ->and($event->reversal_user_point_id)->toBeNull();
    $this->assertDatabaseCount('user_points', 0);
});
test('reversal is refused when original booking is inconsistent', function (): void {
    [$user, $link] = $this->linkedMember();
    $article = $this->contribution($link, ['type' => MaddraxikonContributionType::New]);
    $service = $this->service($this->apiForNewArticle($article));
    $service->evaluate();
    $event = MaddraxikonRewardEvent::query()->sole();
    UserPoint::query()->whereKey($event->user_point_id)->update(['points' => $event->awarded_points - 1]);
    try {
        $service->reverse($event, $this->admin(), 'Darf eine veränderte Originalbuchung nicht ausgleichen');
        $this->fail('A reversal of an inconsistent booking must fail.');
    } catch (LogicException $exception) {
        expect($exception->getMessage())->toContain('originale Baxx-Buchung fehlt oder ist inkonsistent');
    }
    $event->refresh();
    expect($event->status)->toBe(MaddraxikonRewardEventStatus::Awarded)
        ->and($event->reversal_user_point_id)->toBeNull()
        ->and((int) UserPoint::query()->where('user_id', $user->id)->sum('points'))->toBe(4);
});
test('reversal rejects non admin even when called outside admin ui', function (): void {
    [, $link] = $this->linkedMember();
    $article = $this->contribution($link, ['type' => MaddraxikonContributionType::New]);
    $service = $this->service($this->apiForNewArticle($article));
    $service->evaluate();
    expect(fn () => $service->reverse(MaddraxikonRewardEvent::query()->sole(), User::factory()->create(), 'Unberechtigter Versuch'))->toThrow(LogicException::class, 'Nur Administratoren');
});
test('reversal requires reason', function (): void {
    $this->expectException(InvalidArgumentException::class);
    $api = Mockery::mock(MaddraxikonApiClient::class);
    $this->service($api)->reverse(new MaddraxikonRewardEvent, User::factory()->create(), '  ');
});
test('versioned edit policy awards highest net byte tier and snapshots decision', function (): void {
    [$user, $link] = $this->linkedMember();
    $policy = $this->publishedPolicyWithTiers([[100, 1], [500, 3], [1000, 5]]);
    $contribution = $this->contribution($link, ['old_size' => 600, 'new_size' => 1350]);
    $this->completeWatermark();
    expect($this->service($this->apiWithValidRevisions(collect([$contribution])))->evaluate())->toBe(1);
    $event = MaddraxikonRewardEvent::query()->sole();
    expect($event->maddraxikon_reward_policy_id)->toBe($policy->id)
        ->and($event->measured_added_bytes)->toBe(750)
        ->and($event->matched_minimum_added_bytes)->toBe(500)
        ->and($event->candidate_points)->toBe(3)
        ->and($event->awarded_points)->toBe(3)
        ->and($event->calculation_mode)->toBe('byte_tier')
        ->and($event->policy_effective_from_epoch)->toBe($policy->effective_from?->getTimestamp());
    $this->assertDatabaseHas('user_points', ['user_id' => $user->id, 'points' => 3]);
});
test('versioned edit policy counts only valid member revision deltas', function (): void {
    [, $link] = $this->linkedMember();
    $this->publishedPolicyWithTiers([[200, 2], [500, 5]]);
    $first = $this->contribution($link, ['page_id' => 123, 'old_size' => 1000, 'new_size' => 1100, 'occurred_at' => now()->subDays(2)->startOfDay()->addHour()]);
    $reverted = $this->contribution($link, ['page_id' => 123, 'old_size' => 1100, 'new_size' => 1600, 'occurred_at' => now()->subDays(2)->startOfDay()->addHour()->addMinutes(10), 'session_anchor_revision_id' => $first->revision_id]);
    $last = $this->contribution($link, ['page_id' => 123, 'old_size' => 1600, 'new_size' => 1700, 'occurred_at' => now()->subDays(2)->startOfDay()->addHour()->addMinutes(20), 'session_anchor_revision_id' => $first->revision_id]);
    $first->update(['session_anchor_revision_id' => $first->revision_id]);
    $this->completeWatermark();
    $api = Mockery::mock(MaddraxikonApiClient::class);
    $api->shouldReceive('revisionDetails')->once()->andReturn([$first->revision_id => $this->revisionFor($first), $reverted->revision_id => $this->revisionFor($reverted, ['tags' => ['mw-reverted']]), $last->revision_id => $this->revisionFor($last)]);
    expect($this->service($api)->evaluate())->toBe(1);
    $event = MaddraxikonRewardEvent::query()->sole();
    expect($event->measured_added_bytes)->toBe(200)
        ->and($event->matched_minimum_added_bytes)->toBe(200)
        ->and($event->candidate_points)->toBe(2)
        ->and($event->awarded_points)->toBe(2);
    $this->assertDatabaseHas('maddraxikon_contributions', ['id' => $reverted->id, 'status' => MaddraxikonContributionStatus::Rejected->value, 'status_reason' => 'revision_reverted']);
});
test('versioned edit policy records below threshold without award', function (): void {
    [, $link] = $this->linkedMember();
    $this->publishedPolicyWithTiers([[100, 2]]);
    $contribution = $this->contribution($link, ['old_size' => 600, 'new_size' => 699]);
    $this->completeWatermark();
    expect($this->service($this->apiWithValidRevisions(collect([$contribution])))->evaluate())->toBe(1);
    $this->assertDatabaseHas('maddraxikon_reward_events', ['measured_added_bytes' => 99, 'candidate_points' => 0, 'awarded_points' => 0, 'status' => MaddraxikonRewardEventStatus::EvaluatedNoAward->value, 'status_reason' => 'below_minimum_edit_size', 'calculation_mode' => 'byte_tier']);
});
test('policy published after activity does not reclassify legacy session', function (): void {
    [, $link] = $this->linkedMember();
    BaxxEarningRule::query()->where('action_key', MaddraxikonRewardEvent::ACTION_EDIT_SESSION)->update(['points' => 1, 'every_count' => 1, 'is_active' => true]);
    $this->publishedPolicyWithTiers([[0, 9]], ['effective_from' => now()->subHour()]);
    $contribution = $this->contribution($link, ['occurred_at' => now()->subHours(30), 'old_size' => 100, 'new_size' => 5000]);
    $this->completeWatermark();
    expect($this->service($this->apiWithValidRevisions(collect([$contribution])))->evaluate())->toBe(1);
    $this->assertDatabaseHas('maddraxikon_reward_events', ['source_key' => 'edit-session:'.$contribution->revision_id, 'maddraxikon_reward_policy_id' => null, 'candidate_points' => 1, 'awarded_points' => 1, 'calculation_mode' => 'legacy']);
});
test('daily cap is applied after byte tier calculation', function (): void {
    [, $link] = $this->linkedMember();
    config(['maddraxikon.daily_point_cap' => 10]);
    $this->publishedPolicyWithTiers([[0, 15]]);
    $contribution = $this->contribution($link, ['old_size' => 100, 'new_size' => 200]);
    $this->completeWatermark();
    expect($this->service($this->apiWithValidRevisions(collect([$contribution])))->evaluate())->toBe(1);
    $this->assertDatabaseHas('maddraxikon_reward_events', ['candidate_points' => 15, 'awarded_points' => 10, 'capped_points' => 5, 'status_reason' => 'daily_cap_partially_applied']);
});
test('versioned policy can disable edit sessions without rejecting contribution', function (): void {
    [, $link] = $this->linkedMember();
    $this->publishedPolicyWithTiers([[0, 5]], ['edit_sessions_enabled' => false]);
    $contribution = $this->contribution($link, ['old_size' => 100, 'new_size' => 5000]);
    $this->completeWatermark();
    expect($this->service($this->apiWithValidRevisions(collect([$contribution])))->evaluate())->toBe(1);
    $this->assertDatabaseHas('maddraxikon_reward_events', ['status' => MaddraxikonRewardEventStatus::EvaluatedNoAward->value, 'status_reason' => 'edit_sessions_disabled']);
    $this->assertDatabaseHas('maddraxikon_contributions', ['id' => $contribution->id, 'status' => MaddraxikonContributionStatus::Qualified->value]);
});
test('versioned new article policy controls minimum size and points', function (): void {
    [$user, $link] = $this->linkedMember();
    $policy = $this->publishedPolicyWithTiers([[100, 1]], ['new_article_minimum_bytes' => 900, 'new_article_points' => 7]);
    $article = $this->contribution($link, ['type' => MaddraxikonContributionType::New, 'parent_revision_id' => 0]);
    expect($this->service($this->apiForNewArticle($article, pageOverrides: ['size' => 900]))->evaluate())->toBe(1);
    $this->assertDatabaseHas('maddraxikon_reward_events', ['maddraxikon_reward_policy_id' => $policy->id, 'policy_new_article_minimum_bytes' => 900, 'candidate_points' => 7, 'awarded_points' => 7, 'calculation_mode' => 'byte_tier']);
    $this->assertDatabaseHas('user_points', ['user_id' => $user->id, 'points' => 7]);
});
test('disabled new article policy records qualified no award regardless of size', function (): void {
    [, $link] = $this->linkedMember();
    $this->publishedPolicyWithTiers([[100, 1]], ['new_articles_enabled' => false, 'new_article_minimum_bytes' => 10000, 'new_article_points' => 9]);
    $article = $this->contribution($link, ['type' => MaddraxikonContributionType::New, 'parent_revision_id' => 0]);
    expect($this->service($this->apiForNewArticle($article, pageOverrides: ['size' => 50]))->evaluate())->toBe(1);
    $this->assertDatabaseHas('maddraxikon_reward_events', ['source_key' => 'new:'.$article->revision_id, 'status' => MaddraxikonRewardEventStatus::EvaluatedNoAward->value, 'status_reason' => 'new_articles_disabled', 'candidate_points' => 0]);
    $this->assertDatabaseHas('maddraxikon_contributions', ['id' => $article->id, 'status' => MaddraxikonContributionStatus::Qualified->value]);
});
test('missing edit boundary size stays pending as technical failure', function (): void {
    [, $link] = $this->linkedMember();
    $this->publishedPolicyWithTiers([[0, 1]]);
    $contribution = $this->contribution($link, ['old_size' => null, 'new_size' => 700]);
    $this->completeWatermark();
    expect($this->service($this->apiWithValidRevisions(collect([$contribution])))->evaluate())->toBe(0);
    $contribution->refresh();
    expect($contribution->status)->toBe(MaddraxikonContributionStatus::Pending)
        ->and($contribution->evaluation_attempts)->toBe(1)
        ->and((string) $contribution->last_evaluation_error)->toContain('revision_size_unavailable');
    $this->assertDatabaseCount('maddraxikon_reward_events', 0);
});
