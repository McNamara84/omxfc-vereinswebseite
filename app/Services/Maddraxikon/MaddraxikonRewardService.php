<?php

namespace App\Services\Maddraxikon;

use App\Enums\MaddraxikonAccountLinkStatus;
use App\Enums\MaddraxikonContributionStatus;
use App\Enums\MaddraxikonContributionType;
use App\Enums\MaddraxikonRewardEventStatus;
use App\Enums\Role;
use App\Models\Activity;
use App\Models\BaxxEarningRule;
use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonContribution;
use App\Models\MaddraxikonRewardEvent;
use App\Models\MaddraxikonSyncState;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPoint;
use App\Services\LockedMembersTeamMemberships;
use App\Services\Maddraxikon\Exceptions\RecoveryRequiredException;
use App\Services\MembersTeamMembershipLock;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use Throwable;

class MaddraxikonRewardService
{
    public function __construct(
        private readonly MaddraxikonApiClient $apiClient,
        ?MembersTeamMembershipLock $membershipLock = null,
    ) {
        $this->membershipLock = $membershipLock
            ?? app(MembersTeamMembershipLock::class);
    }

    private readonly MembersTeamMembershipLock $membershipLock;

    /**
     * Evaluate every currently due contribution once.
     *
     * @return int Number of newly written reward-ledger entries.
     */
    public function evaluate(bool $force = false, ?int $onlyContributionId = null): int
    {
        if (! $force && ! config('maddraxikon.features.awards_enabled', false)) {
            return 0;
        }

        if (! $force && ! config('maddraxikon.features.sync_enabled', false)) {
            throw new LogicException(
                'Maddraxikon-Auszahlungen erfordern einen aktiven Import.'
            );
        }

        $membersTeam = Team::membersTeam();

        if (! $membersTeam) {
            throw new LogicException(
                'Das Mitglieder-Team fehlt; Maddraxikon-Baxx können nicht verbucht werden.'
            );
        }

        $syncState = MaddraxikonSyncState::query()
            ->where(
                'wiki_key',
                (string) config('maddraxikon.wiki_key', 'maddraxikon-de')
            )
            ->first();

        if ($syncState?->recovery_required_at !== null) {
            throw new RecoveryRequiredException;
        }

        $query = MaddraxikonContribution::query()->due();
        $targetSourceKey = null;

        if ($onlyContributionId !== null) {
            $target = (clone $query)->whereKey($onlyContributionId)->first();

            if (! $target) {
                return 0;
            }

            $targetSourceKey = $this->sourceKey($target);
            $query->where('user_id', $target->user_id);
        }

        $sources = $this->dueSources(
            $query,
            max(1, (int) config('maddraxikon.evaluation.source_batch_size', 100))
        );

        if ($targetSourceKey !== null) {
            $first = $sources->first();

            if (
                ! $first
                || $this->sourceKey($first['source']) !== $targetSourceKey
            ) {
                return 0;
            }

            $sources = $sources->filter(
                fn (array $candidate): bool => (
                    $this->sourceKey($candidate['source']) === $targetSourceKey
                )
            );
        }

        $validation = $this->prefetchRemoteValidation($sources, $membersTeam);

        $evaluated = 0;
        $blockedUserIds = [];
        $awardedPointsByUser = [];
        $recordAward = static function (
            int $userId,
            int $awardedPoints
        ) use (&$awardedPointsByUser): void {
            if ($awardedPoints < 1) {
                return;
            }

            $awardedPointsByUser[$userId] = (
                $awardedPointsByUser[$userId] ?? 0
            ) + $awardedPoints;
        };

        foreach ($sources as $candidate) {
            /** @var MaddraxikonContribution $contribution */
            $contribution = $candidate['source'];

            if (isset($blockedUserIds[$contribution->user_id])) {
                continue;
            }

            try {
                if ($contribution->type === MaddraxikonContributionType::New) {
                    $result = $this->evaluateNewArticle(
                        $contribution,
                        $membersTeam,
                        $validation['revisions'],
                        $validation['pages'],
                        $recordAward
                    );
                    $evaluated += $result;

                } else {
                    $result = $this->evaluateEditSession(
                        $contribution,
                        $membersTeam,
                        $validation['revisions'],
                        $recordAward
                    );
                    $evaluated += $result;
                }

                if (
                    $result === 0
                    && MaddraxikonContribution::query()
                        ->whereKey($contribution->id)
                        ->where('status', MaddraxikonContributionStatus::Pending)
                        ->exists()
                ) {
                    $blockedUserIds[$contribution->user_id] = true;
                }
            } catch (RecoveryRequiredException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                report($exception);
                $this->recordEvaluationFailure($contribution, $exception);
                $blockedUserIds[$contribution->user_id] = true;
            }
        }

        foreach ($awardedPointsByUser as $userId => $awardedPoints) {
            Activity::query()->create([
                'user_id' => $userId,
                'subject_type' => User::class,
                'subject_id' => $userId,
                'action' => Activity::ACTION_MADDRAXIKON_BAXX_AWARDED_PREFIX
                    .$awardedPoints,
            ]);
        }

        return $evaluated;
    }

    public function reverse(
        MaddraxikonRewardEvent $rewardEvent,
        User $admin,
        string $reason
    ): MaddraxikonRewardEvent {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException(
                'Für eine Baxx-Korrektur ist eine Begründung erforderlich.'
            );
        }

        $membersTeam = Team::membersTeam();

        if (! $membersTeam) {
            throw new LogicException(
                'Das Mitglieder-Team fehlt; die Baxx-Korrektur kann nicht verbucht werden.'
            );
        }

        if (! $membersTeam->hasUserWithRole($admin, Role::Admin->value)) {
            throw new LogicException(
                'Nur Administratoren dürfen Maddraxikon-Baxx gegenbuchen.'
            );
        }

        return $this->membershipLock->run(
            [$admin->id, $rewardEvent->user_id],
            function (LockedMembersTeamMemberships $memberships) use (
                $rewardEvent,
                $admin,
                $reason,
            ): MaddraxikonRewardEvent {
                if (! $memberships->hasRole($admin->id, Role::Admin)) {
                    throw new LogicException(
                        'Die Admin-Berechtigung fuer die Baxx-Korrektur fehlt.'
                    );
                }

                $membersTeam = $memberships->team;

                $lockedEvent = MaddraxikonRewardEvent::query()
                    ->whereKey($rewardEvent->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $lockedEvent->status !== MaddraxikonRewardEventStatus::Awarded
                    || $lockedEvent->awarded_points < 1
                    || $lockedEvent->reversal_user_point_id !== null
                ) {
                    throw new LogicException(
                        'Diese Maddraxikon-Gutschrift kann nicht korrigiert werden.'
                    );
                }

                $originalBooking = $lockedEvent->user_point_id === null
                    ? null
                    : UserPoint::query()
                        ->whereKey($lockedEvent->user_point_id)
                        ->lockForUpdate()
                        ->first();

                if (
                    ! $originalBooking
                    || (int) $originalBooking->user_id !== (int) $lockedEvent->user_id
                    || (int) $originalBooking->team_id !== (int) $membersTeam->id
                    || (int) $originalBooking->points !== (int) $lockedEvent->awarded_points
                ) {
                    throw new LogicException(
                        'Die originale Baxx-Buchung fehlt oder ist inkonsistent; eine sichere Gegenbuchung ist nicht möglich.'
                    );
                }

                $reversal = UserPoint::query()->create([
                    'user_id' => $lockedEvent->user_id,
                    'team_id' => $membersTeam->id,
                    'points' => -$lockedEvent->awarded_points,
                ]);

                $lockedEvent->update([
                    'status' => MaddraxikonRewardEventStatus::Reversed,
                    'reversal_user_point_id' => $reversal->id,
                    'reversed_at' => now(),
                    'reversed_by' => $admin->id,
                    'reversal_reason' => $reason,
                ]);

                return $lockedEvent->fresh();
            });
    }

    /**
     * Resolve a bounded, chronological list of logical reward sources.
     *
     * The aggregation keeps complete edit sessions together and orders them by
     * their last activity, matching the sequence semantics used for awards.
     *
     * @param  Builder<MaddraxikonContribution>  $query
     * @return Collection<int, array{
     *     source: MaddraxikonContribution,
     *     activity_at: CarbonImmutable,
     *     revision_id: int
     * }>
     */
    private function dueSources(Builder $query, int $limit): Collection
    {
        $newType = MaddraxikonContributionType::New->value;
        $sourceExpression = "CASE WHEN type = '{$newType}' ".
            'THEN revision_id ELSE COALESCE(session_anchor_revision_id, revision_id) END';

        $groups = (clone $query)
            ->reorder()
            ->select([
                'wiki_key',
                'user_id',
                'account_link_id',
                'page_id',
                'type',
            ])
            ->selectRaw($sourceExpression.' AS source_group_revision_id')
            ->selectRaw('MAX(COALESCE(occurred_at_epoch, 0)) AS activity_at_epoch')
            ->selectRaw('MAX(occurred_at) AS activity_at_value')
            ->selectRaw('MAX(revision_id) AS ordering_revision_id')
            ->groupBy(
                'wiki_key',
                'user_id',
                'account_link_id',
                'page_id',
                'type',
                DB::raw($sourceExpression)
            )
            ->orderBy('activity_at_epoch')
            ->orderBy('activity_at_value')
            ->orderBy('ordering_revision_id')
            ->limit($limit)
            ->get();

        return $groups
            ->map(function (MaddraxikonContribution $group): ?array {
                $source = $this->sourceContributionForGroup($group);

                if (! $source) {
                    return null;
                }

                $activityEpoch = (int) $group->getAttribute('activity_at_epoch');
                $activityAt = $activityEpoch > 0
                    ? CarbonImmutable::createFromTimestampUTC($activityEpoch)
                    : CarbonImmutable::parse(
                        (string) $group->getAttribute('activity_at_value'),
                        'UTC'
                    );

                return [
                    'source' => $source,
                    'activity_at' => $activityAt,
                    'revision_id' => (int) $group->getAttribute(
                        'ordering_revision_id'
                    ),
                ];
            })
            ->filter()
            ->values();
    }

    private function sourceContributionForGroup(
        MaddraxikonContribution $group
    ): ?MaddraxikonContribution {
        $sourceRevisionId = (int) $group->getAttribute(
            'source_group_revision_id'
        );
        $query = MaddraxikonContribution::query()
            ->due()
            ->where('wiki_key', $group->wiki_key)
            ->where('user_id', $group->user_id)
            ->where('account_link_id', $group->account_link_id)
            ->where('page_id', $group->page_id)
            ->where('type', $group->type);

        if ($group->type === MaddraxikonContributionType::New) {
            $query->where('revision_id', $sourceRevisionId);
        } else {
            $query->where(function (Builder $query) use (
                $sourceRevisionId
            ): void {
                $query
                    ->where(
                        'session_anchor_revision_id',
                        $sourceRevisionId
                    )
                    ->orWhere(function (Builder $query) use (
                        $sourceRevisionId
                    ): void {
                        $query
                            ->whereNull('session_anchor_revision_id')
                            ->where('revision_id', $sourceRevisionId);
                    });
            });
        }

        return $query
            ->orderBy('occurred_at_epoch')
            ->orderBy('occurred_at')
            ->orderBy('revision_id')
            ->first();
    }

    /**
     * Fetch immutable MediaWiki validation data once per run. IDs are bundled
     * into explicitly bounded chunks even when several sources share a batch.
     *
     * @param  Collection<int, array{
     *     source: MaddraxikonContribution,
     *     activity_at: CarbonImmutable,
     *     revision_id: int
     * }>  $sources
     * @return array{
     *     revisions: array<int, array<string, mixed>>,
     *     pages: array<int, array<string, mixed>>
     * }
     */
    private function prefetchRemoteValidation(
        Collection $sources,
        Team $membersTeam
    ): array {
        $revisionOwners = [];
        $newArticleSources = [];

        foreach ($sources as $candidate) {
            /** @var MaddraxikonContribution $source */
            $source = $candidate['source'];

            if (
                $this->rewardExists($source->wiki_key, $this->sourceKey($source))
                || $this->linkFailureReason($source, $membersTeam) !== null
            ) {
                continue;
            }

            if ($source->type === MaddraxikonContributionType::New) {
                if (
                    $source->namespace_id !== (int) config(
                        'maddraxikon.article_namespace',
                        0
                    )
                ) {
                    continue;
                }

                $revisionOwners[$source->revision_id] = $source;
                $newArticleSources[$source->revision_id] = $source;

                continue;
            }

            $session = $this->pendingEditSession($source);

            if ($session->isEmpty() || ! $this->sessionWatermarkIsComplete($session)) {
                continue;
            }

            foreach ($session as $contribution) {
                $revisionOwners[$contribution->revision_id] = $source;
            }
        }

        $batchSize = min(
            50,
            max(1, (int) config('maddraxikon.evaluation.api_batch_size', 50))
        );
        $revisionDetails = [];

        foreach (array_chunk(array_keys($revisionOwners), $batchSize) as $ids) {
            try {
                $revisionDetails = array_replace(
                    $revisionDetails,
                    $this->apiClient->revisionDetails($ids)
                );
            } catch (Throwable $exception) {
                $this->recordApiBatchFailure(
                    $revisionOwners[$ids[0]],
                    $exception
                );

                throw $exception;
            }
        }

        $pageOwners = [];

        foreach ($newArticleSources as $revisionId => $source) {
            if (
                $this->revisionFailureReason(
                    $source,
                    $revisionDetails[$revisionId] ?? null
                ) === null
            ) {
                $pageOwners[$source->page_id] ??= $source;
            }
        }

        $pageDetails = [];

        foreach (array_chunk(array_keys($pageOwners), $batchSize) as $ids) {
            try {
                $pageDetails = array_replace(
                    $pageDetails,
                    $this->apiClient->pageDetails($ids)
                );
            } catch (Throwable $exception) {
                $this->recordApiBatchFailure(
                    $pageOwners[$ids[0]],
                    $exception
                );

                throw $exception;
            }
        }

        return [
            'revisions' => $revisionDetails,
            'pages' => $pageDetails,
        ];
    }

    private function recordApiBatchFailure(
        MaddraxikonContribution $contribution,
        Throwable $exception
    ): void {
        report($exception);
        $this->recordEvaluationFailure($contribution, $exception);
    }

    /**
     * @param  array<int, array<string, mixed>>  $revisionDetails
     * @param  array<int, array<string, mixed>>  $pageDetails
     */
    private function evaluateNewArticle(
        MaddraxikonContribution $contribution,
        Team $membersTeam,
        array $revisionDetails,
        array $pageDetails,
        Closure $onAward
    ): int {
        $sourceKey = $this->sourceKey($contribution);

        if ($this->rewardExists($contribution->wiki_key, $sourceKey)) {
            return 0;
        }

        $linkFailure = $this->linkFailureReason($contribution, $membersTeam);

        if ($linkFailure !== null) {
            return $this->reject(
                collect([$contribution]),
                MaddraxikonRewardEvent::ACTION_NEW_ARTICLE,
                $sourceKey,
                $linkFailure
            );
        }

        if (
            $contribution->namespace_id !== (int) config(
                'maddraxikon.article_namespace',
                0
            )
        ) {
            return $this->reject(
                collect([$contribution]),
                MaddraxikonRewardEvent::ACTION_NEW_ARTICLE,
                $sourceKey,
                'source_namespace_not_article'
            );
        }

        if (! array_key_exists($contribution->revision_id, $revisionDetails)) {
            return 0;
        }

        $revision = $revisionDetails[$contribution->revision_id];
        $revisionFailure = $this->revisionFailureReason($contribution, $revision);

        if ($revisionFailure !== null) {
            return $this->reject(
                collect([$contribution]),
                MaddraxikonRewardEvent::ACTION_NEW_ARTICLE,
                $sourceKey,
                $revisionFailure
            );
        }

        if (! array_key_exists($contribution->page_id, $pageDetails)) {
            return 0;
        }

        $page = $pageDetails[$contribution->page_id];
        $pageFailure = $this->newArticlePageFailureReason($page);

        if ($pageFailure !== null) {
            return $this->reject(
                collect([$contribution]),
                MaddraxikonRewardEvent::ACTION_NEW_ARTICLE,
                $sourceKey,
                $pageFailure
            );
        }

        return $this->awardQualifiedSource(
            collect([$contribution]),
            MaddraxikonRewardEvent::ACTION_NEW_ARTICLE,
            $sourceKey,
            $contribution,
            $contribution->occurredAtUtc(),
            onAward: $onAward
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $revisionDetails
     */
    private function evaluateEditSession(
        MaddraxikonContribution $seed,
        Team $membersTeam,
        array $revisionDetails,
        Closure $onAward
    ): int {
        $anchorRevisionId = $seed->session_anchor_revision_id ?? $seed->revision_id;
        $sourceKey = 'edit-session:'.$anchorRevisionId;

        if ($this->rewardExists($seed->wiki_key, $sourceKey)) {
            return 0;
        }

        $session = $this->pendingEditSession($seed);

        if ($session->isEmpty()) {
            return 0;
        }

        if (! $this->sessionWatermarkIsComplete($session)) {
            return 0;
        }

        $linkFailure = $this->linkFailureReason($seed, $membersTeam);

        if ($linkFailure !== null) {
            return $this->reject(
                $session,
                MaddraxikonRewardEvent::ACTION_EDIT_SESSION,
                $sourceKey,
                $linkFailure
            );
        }

        if ($session->contains(
            fn (MaddraxikonContribution $contribution): bool => (
                ! array_key_exists($contribution->revision_id, $revisionDetails)
            )
        )) {
            return 0;
        }

        $valid = collect();
        $invalid = collect();

        foreach ($session as $contribution) {
            $failure = $this->revisionFailureReason(
                $contribution,
                $revisionDetails[$contribution->revision_id] ?? null
            );

            if ($failure === null) {
                $valid->push($contribution);
            } else {
                $invalid->put($contribution->id, $failure);
            }
        }

        if ($valid->isEmpty()) {
            $reason = $invalid->first() ?? 'revision_not_eligible';

            return $this->reject(
                $session,
                MaddraxikonRewardEvent::ACTION_EDIT_SESSION,
                $sourceKey,
                $reason
            );
        }

        $lastContribution = $session->last();

        return $this->awardQualifiedSource(
            $valid,
            MaddraxikonRewardEvent::ACTION_EDIT_SESSION,
            $sourceKey,
            $valid->first(),
            $lastContribution->occurredAtUtc(),
            $invalid->all(),
            $onAward
        );
    }

    /**
     * @return Collection<int, MaddraxikonContribution>
     */
    private function pendingEditSession(
        MaddraxikonContribution $seed
    ): Collection {
        $anchorRevisionId = $seed->session_anchor_revision_id ?? $seed->revision_id;

        return MaddraxikonContribution::query()
            ->where('wiki_key', $seed->wiki_key)
            ->where('account_link_id', $seed->account_link_id)
            ->where('page_id', $seed->page_id)
            ->where('type', MaddraxikonContributionType::Edit)
            ->where('status', MaddraxikonContributionStatus::Pending)
            ->where(function (Builder $query) use (
                $anchorRevisionId
            ): void {
                $query
                    ->where('session_anchor_revision_id', $anchorRevisionId)
                    ->orWhere(function (Builder $query) use (
                        $anchorRevisionId
                    ): void {
                        $query
                            ->whereNull('session_anchor_revision_id')
                            ->where('revision_id', $anchorRevisionId);
                    });
            })
            ->orderBy('occurred_at_epoch')
            ->orderBy('occurred_at')
            ->orderBy('revision_id')
            ->get();
    }

    /**
     * @param  Collection<int, MaddraxikonContribution>  $contributions
     * @param  array<int, string>  $invalidReasons
     */
    private function awardQualifiedSource(
        Collection $contributions,
        string $actionKey,
        string $sourceKey,
        MaddraxikonContribution $source,
        CarbonImmutable $activityAt,
        array $invalidReasons = [],
        ?Closure $onAward = null
    ): int {
        $award = null;
        $evaluated = $this->withRewardDecisionLocks(
            [$source->user_id],
            $source->wiki_key,
            function (LockedMembersTeamMemberships $memberships) use (
                $contributions,
                $actionKey,
                $sourceKey,
                $source,
                $activityAt,
                $invalidReasons,
                &$award
            ): int {
                if ($this->rewardExists($source->wiki_key, $sourceKey, true)) {
                    return 0;
                }

                $contributionIds = $contributions->pluck('id')
                    ->merge(array_keys($invalidReasons))
                    ->unique();
                $lockedContributions = MaddraxikonContribution::query()
                    ->whereKey($contributionIds)
                    ->lockForUpdate()
                    ->get();

                if (
                    $lockedContributions->isEmpty()
                    || $lockedContributions->contains(
                        fn (MaddraxikonContribution $item): bool => (
                            $item->status !== MaddraxikonContributionStatus::Pending
                        )
                    )
                ) {
                    return 0;
                }

                $link = MaddraxikonAccountLink::query()
                    ->whereKey($source->account_link_id)
                    ->lockForUpdate()
                    ->first();

                if (
                    ! $link
                    || $link->status !== MaddraxikonAccountLinkStatus::Active
                    || $link->disconnected_at !== null
                    || $link->user_id !== $source->user_id
                    || $link->wiki_user_id !== $source->wiki_user_id
                    || $link->verified_at === null
                    || $source->occurredAtUtc()->lt($link->verified_at)
                ) {
                    return $this->rejectLocked(
                        $lockedContributions,
                        $actionKey,
                        $sourceKey,
                        $source,
                        'account_link_inactive',
                        $activityAt
                    );
                }

                $membersTeam = Team::membersTeam();

                if (! $membersTeam) {
                    throw new LogicException(
                        'Das Mitglieder-Team fehlt; Maddraxikon-Baxx können nicht verbucht werden.'
                    );
                }

                if (! $memberships->isActiveMember($source->user_id)) {
                    return $this->rejectLocked(
                        $lockedContributions,
                        $actionKey,
                        $sourceKey,
                        $source,
                        'membership_inactive',
                        $activityAt
                    );
                }

                $rule = BaxxEarningRule::query()
                    ->where('action_key', $actionKey)
                    ->lockForUpdate()
                    ->first();

                if (! $rule) {
                    throw new LogicException(
                        "Die Baxx-Regel {$actionKey} fehlt; der Beitrag bleibt zur späteren Prüfung offen."
                    );
                }

                $sequenceNumber = ((int) MaddraxikonRewardEvent::query()
                    ->where('user_id', $source->user_id)
                    ->where('action_key', $actionKey)
                    ->max('sequence_number')) + 1;
                $rulePoints = max(0, (int) ($rule?->points ?? 0));
                $ruleEveryCount = max(1, (int) ($rule?->every_count ?? 1));
                $candidatePoints = $rule?->is_active === true
                    && $sequenceNumber % $ruleEveryCount === 0
                    ? $rulePoints
                    : 0;
                $activityDate = $activityAt
                    ->setTimezone((string) config('maddraxikon.timezone', 'Europe/Berlin'))
                    ->toDateString();
                $alreadyAwarded = (int) MaddraxikonRewardEvent::query()
                    ->where('user_id', $source->user_id)
                    ->whereDate('activity_date', $activityDate)
                    ->sum('awarded_points');
                $dailyCap = max(0, (int) config('maddraxikon.daily_point_cap', 10));
                $awardedPoints = min(
                    $candidatePoints,
                    max(0, $dailyCap - $alreadyAwarded)
                );
                $cappedPoints = $candidatePoints - $awardedPoints;
                $statusReason = $this->awardStatusReason(
                    $rule,
                    $candidatePoints,
                    $awardedPoints,
                    $cappedPoints
                );
                $userPoint = null;

                if ($awardedPoints > 0) {
                    $membersTeam = Team::membersTeam();

                    if (! $membersTeam) {
                        throw new LogicException(
                            'Das Mitglieder-Team fehlt; Maddraxikon-Baxx können nicht verbucht werden.'
                        );
                    }

                    if (! $memberships->isActiveMember($source->user_id)) {
                        return $this->rejectLocked(
                            $lockedContributions,
                            $actionKey,
                            $sourceKey,
                            $source,
                            'membership_inactive',
                            $activityAt
                        );
                    }

                    $userPoint = UserPoint::query()->create([
                        'user_id' => $source->user_id,
                        'team_id' => $membersTeam->id,
                        'points' => $awardedPoints,
                    ]);
                }

                $event = MaddraxikonRewardEvent::query()->create([
                    'wiki_key' => $source->wiki_key,
                    'user_id' => $source->user_id,
                    'account_link_id' => $source->account_link_id,
                    'source_contribution_id' => $source->id,
                    'action_key' => $actionKey,
                    'source_key' => $sourceKey,
                    'source_revision_id' => $source->revision_id,
                    'session_anchor_revision_id' => $actionKey
                        === MaddraxikonRewardEvent::ACTION_EDIT_SESSION
                            ? ($source->session_anchor_revision_id ?? $source->revision_id)
                            : null,
                    'activity_date' => $activityDate,
                    'sequence_number' => $sequenceNumber,
                    'baxx_earning_rule_id' => $rule?->id,
                    'rule_points' => $rulePoints,
                    'rule_every_count' => $ruleEveryCount,
                    'rule_updated_at' => $rule?->updated_at,
                    'candidate_points' => $candidatePoints,
                    'awarded_points' => $awardedPoints,
                    'capped_points' => $cappedPoints,
                    'status' => $awardedPoints > 0
                        ? MaddraxikonRewardEventStatus::Awarded
                        : MaddraxikonRewardEventStatus::EvaluatedNoAward,
                    'status_reason' => $statusReason,
                    'user_point_id' => $userPoint?->id,
                    'awarded_at' => $awardedPoints > 0 ? now() : null,
                ]);

                $lockedContributions->each(function (
                    MaddraxikonContribution $contribution
                ) use ($event, $invalidReasons): void {
                    $isInvalid = array_key_exists($contribution->id, $invalidReasons);

                    $contribution->update([
                        'status' => $isInvalid
                            ? MaddraxikonContributionStatus::Rejected
                            : ($event->awarded_points > 0
                            ? MaddraxikonContributionStatus::Awarded
                            : MaddraxikonContributionStatus::Qualified),
                        'status_reason' => $isInvalid
                            ? $invalidReasons[$contribution->id]
                            : $event->status_reason,
                        'last_evaluation_error' => null,
                        'last_evaluation_error_at' => null,
                        'checked_at' => now(),
                    ]);
                });

                if ($event->awarded_points > 0) {
                    $award = [
                        'user_id' => (int) $event->user_id,
                        'points' => (int) $event->awarded_points,
                    ];
                }

                return 1;
            });

        if ($award !== null && $onAward !== null) {
            $onAward($award['user_id'], $award['points']);
        }

        return $evaluated;
    }

    /**
     * @param  Collection<int, MaddraxikonContribution>  $contributions
     */
    private function reject(
        Collection $contributions,
        string $actionKey,
        string $sourceKey,
        string $reason
    ): int {
        $source = $contributions->first();

        if (! $source instanceof MaddraxikonContribution) {
            return 0;
        }

        $activityAt = $actionKey === MaddraxikonRewardEvent::ACTION_EDIT_SESSION
            ? $contributions->last()->occurredAtUtc()
            : $source->occurredAtUtc();

        return $this->withRewardDecisionLocks(
            [$source->user_id],
            $source->wiki_key,
            function (LockedMembersTeamMemberships $memberships) use (
                $contributions,
                $actionKey,
                $sourceKey,
                $reason,
                $source,
                $activityAt
            ): int {
                if (
                    $reason === 'membership_inactive'
                    && $memberships->isActiveMember($source->user_id)
                ) {
                    return 0;
                }

                if ($this->rewardExists($source->wiki_key, $sourceKey, true)) {
                    return 0;
                }

                $lockedContributions = MaddraxikonContribution::query()
                    ->whereKey($contributions->pluck('id'))
                    ->lockForUpdate()
                    ->get();

                if (
                    $lockedContributions->isEmpty()
                    || $lockedContributions->contains(
                        fn (MaddraxikonContribution $item): bool => (
                            $item->status !== MaddraxikonContributionStatus::Pending
                        )
                    )
                ) {
                    return 0;
                }

                return $this->rejectLocked(
                    $lockedContributions,
                    $actionKey,
                    $sourceKey,
                    $source,
                    $reason,
                    $activityAt
                );
            });
    }

    /**
     * @param  Collection<int, MaddraxikonContribution>  $contributions
     */
    private function rejectLocked(
        Collection $contributions,
        string $actionKey,
        string $sourceKey,
        MaddraxikonContribution $source,
        string $reason,
        CarbonImmutable $activityAt
    ): int {
        MaddraxikonRewardEvent::query()->create([
            'wiki_key' => $source->wiki_key,
            'user_id' => $source->user_id,
            'account_link_id' => $source->account_link_id,
            'source_contribution_id' => $source->id,
            'action_key' => $actionKey,
            'source_key' => $sourceKey,
            'source_revision_id' => $source->revision_id,
            'session_anchor_revision_id' => $actionKey
                === MaddraxikonRewardEvent::ACTION_EDIT_SESSION
                    ? ($source->session_anchor_revision_id ?? $source->revision_id)
                    : null,
            'activity_date' => $activityAt
                ->setTimezone((string) config('maddraxikon.timezone', 'Europe/Berlin'))
                ->toDateString(),
            'sequence_number' => null,
            'rule_points' => 0,
            'rule_every_count' => 1,
            'candidate_points' => 0,
            'awarded_points' => 0,
            'capped_points' => 0,
            'status' => MaddraxikonRewardEventStatus::Rejected,
            'status_reason' => $reason,
        ]);

        $contributions->each(function (
            MaddraxikonContribution $contribution
        ) use ($reason): void {
            $contribution->update([
                'status' => MaddraxikonContributionStatus::Rejected,
                'status_reason' => $reason,
                'last_evaluation_error' => null,
                'last_evaluation_error_at' => null,
                'checked_at' => now(),
            ]);
        });

        return 1;
    }

    private function linkFailureReason(
        MaddraxikonContribution $contribution,
        Team $membersTeam
    ): ?string {
        $link = MaddraxikonAccountLink::query()->find($contribution->account_link_id);

        if (
            ! $link
            || ! $link->isActive()
            || $link->user_id !== $contribution->user_id
            || $link->wiki_user_id !== $contribution->wiki_user_id
            || $link->verified_at === null
            || $contribution->occurredAtUtc()->lt($link->verified_at)
        ) {
            return 'account_link_inactive';
        }

        if (! $membersTeam->activeUsers()->where('users.id', $contribution->user_id)->exists()) {
            return 'membership_inactive';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $revision
     */
    private function revisionFailureReason(
        MaddraxikonContribution $contribution,
        ?array $revision
    ): ?string {
        if (! ($revision['exists'] ?? false)) {
            return 'revision_missing';
        }

        if (
            ($revision['page_id'] ?? null) !== $contribution->page_id
            || ($revision['user_id'] ?? null) !== $contribution->wiki_user_id
        ) {
            return 'revision_identity_mismatch';
        }

        if (
            ($revision['user_hidden'] ?? false)
            || ($revision['sha1_hidden'] ?? false)
            || ($revision['text_hidden'] ?? false)
            || ($revision['suppressed'] ?? false)
        ) {
            return 'revision_hidden';
        }

        $tags = array_unique([
            ...($contribution->tags ?? []),
            ...($revision['tags'] ?? []),
        ]);

        if (in_array('mw-reverted', $tags, true)) {
            return 'revision_reverted';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $page
     */
    private function newArticlePageFailureReason(?array $page): ?string
    {
        if (! ($page['exists'] ?? false)) {
            return 'page_missing';
        }

        if (
            ($page['namespace_id'] ?? null)
            !== (int) config('maddraxikon.article_namespace', 0)
        ) {
            return 'not_an_article';
        }

        if ($page['redirect'] ?? false) {
            return 'page_is_redirect';
        }

        if (
            (int) ($page['size'] ?? 0)
            < max(0, (int) config('maddraxikon.minimum_article_bytes', 500))
        ) {
            return 'article_too_short';
        }

        return null;
    }

    /**
     * @param  Collection<int, MaddraxikonContribution>  $session
     */
    private function sessionWatermarkIsComplete(Collection $session): bool
    {
        $last = $session->last();
        $state = MaddraxikonSyncState::query()
            ->where('wiki_key', $last->wiki_key)
            ->first();

        if (! $state?->watermark_at) {
            return false;
        }

        return CarbonImmutable::instance($state->watermark_at)->gte(
            $last->occurredAtUtc()->addMinutes(
                max(1, (int) config('maddraxikon.session_window_minutes', 30))
            )
        );
    }

    /**
     * Keep every reward decision on the same global lock order as imports:
     * sync state, members team, users, membership pivots, contribution rows.
     *
     * @template TResult
     *
     * @param  list<int>  $userIds
     * @param  Closure(LockedMembersTeamMemberships): TResult  $callback
     * @return TResult
     */
    private function withRewardDecisionLocks(
        array $userIds,
        string $wikiKey,
        Closure $callback,
    ): mixed {
        return DB::transaction(function () use (
            $callback,
            $userIds,
            $wikiKey,
        ): mixed {
            $this->ensureRecoveryIsClosed($wikiKey);

            return $this->membershipLock->run(
                $userIds,
                $callback,
                attempts: 1,
            );
        }, attempts: 3);
    }

    private function ensureRecoveryIsClosed(string $wikiKey): void
    {
        $state = MaddraxikonSyncState::query()
            ->where('wiki_key', $wikiKey)
            ->lockForUpdate()
            ->first();

        if ($state?->recovery_required_at !== null) {
            throw new RecoveryRequiredException;
        }
    }

    private function recordEvaluationFailure(
        MaddraxikonContribution $contribution,
        Throwable $exception
    ): void {
        $message = Str::limit(
            class_basename($exception).': '.preg_replace(
                '/\s+/',
                ' ',
                trim($exception->getMessage())
            ),
            1000,
            ''
        );
        $query = MaddraxikonContribution::query()
            ->where('status', MaddraxikonContributionStatus::Pending);

        if ($contribution->type === MaddraxikonContributionType::New) {
            $query->whereKey($contribution->id);
        } else {
            $anchorRevisionId = $contribution->session_anchor_revision_id
                ?? $contribution->revision_id;
            $query
                ->where('wiki_key', $contribution->wiki_key)
                ->where('account_link_id', $contribution->account_link_id)
                ->where('page_id', $contribution->page_id)
                ->where('type', MaddraxikonContributionType::Edit)
                ->where(function ($query) use ($anchorRevisionId): void {
                    $query->where('session_anchor_revision_id', $anchorRevisionId)
                        ->orWhere(function ($query) use ($anchorRevisionId): void {
                            $query->whereNull('session_anchor_revision_id')
                                ->where('revision_id', $anchorRevisionId);
                        });
                });
        }

        $query->update([
            'evaluation_attempts' => DB::raw('evaluation_attempts + 1'),
            'last_evaluation_error' => $message,
            'last_evaluation_error_at' => now(),
        ]);
    }

    private function sourceKey(MaddraxikonContribution $contribution): string
    {
        return $contribution->type === MaddraxikonContributionType::New
            ? 'new:'.$contribution->revision_id
            : 'edit-session:'.
                ($contribution->session_anchor_revision_id ?? $contribution->revision_id);
    }

    private function rewardExists(
        string $wikiKey,
        string $sourceKey,
        bool $lock = false
    ): bool {
        $query = MaddraxikonRewardEvent::query()
            ->where('wiki_key', $wikiKey)
            ->where('source_key', $sourceKey);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->exists();
    }

    private function awardStatusReason(
        ?BaxxEarningRule $rule,
        int $candidatePoints,
        int $awardedPoints,
        int $cappedPoints
    ): ?string {
        if (! $rule) {
            return 'rule_missing';
        }

        if (! $rule->is_active) {
            return 'rule_inactive';
        }

        if ($candidatePoints === 0) {
            return $rule->points < 1
                ? 'rule_has_no_points'
                : 'interval_not_reached';
        }

        if ($awardedPoints === 0 && $cappedPoints > 0) {
            return 'daily_cap_reached';
        }

        if ($cappedPoints > 0) {
            return 'daily_cap_partially_applied';
        }

        return null;
    }
}
