<?php

namespace App\Services\Maddraxikon;

use App\Enums\MaddraxikonContributionStatus;
use App\Enums\MaddraxikonContributionType;
use App\Exceptions\MaddraxikonApiException;
use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonContribution;
use App\Models\MaddraxikonSyncState;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;
use RuntimeException;
use Throwable;

class MaddraxikonContributionImporter
{
    public function __construct(private readonly MaddraxikonApiClient $apiClient) {}

    public function sync(bool $force = false): int
    {
        if (! $force && ! config('maddraxikon.features.sync_enabled', false)) {
            return 0;
        }

        $membersTeam = $this->membersTeamOrFail();
        $wikiKey = (string) config('maddraxikon.wiki_key', 'maddraxikon-de');
        $syncUntil = CarbonImmutable::now()->startOfSecond();
        [$state, $initialized, $runSequence] = DB::transaction(
            function () use ($syncUntil, $wikiKey): array {
                $state = MaddraxikonSyncState::query()->firstOrCreate(
                    ['wiki_key' => $wikiKey],
                    ['watermark_at' => null]
                );
                $lockedState = MaddraxikonSyncState::query()
                    ->whereKey($state->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // A scheduled no-op must not supersede a concurrently running
                // manual recovery and hide its eventual failure state.
                if ($lockedState->recovery_required_at !== null) {
                    $lockedState->update(['last_started_at' => $syncUntil]);

                    return [$lockedState->fresh(), false, $lockedState->run_sequence];
                }

                $initialized = false;
                $runSequence = $lockedState->run_sequence + 1;
                $updates = [
                    'last_started_at' => $syncUntil,
                    'run_sequence' => $runSequence,
                ];

                // Establish the no-backfill boundary while holding the row
                // lock, so two first runs cannot initialize different windows.
                if ($lockedState->watermark_at === null) {
                    $initialized = true;
                    $updates = [
                        ...$updates,
                        'watermark_at' => $syncUntil,
                        'initial_watermark_at' => $syncUntil,
                        'last_succeeded_at' => $syncUntil,
                        'last_error_at' => null,
                        'last_error' => null,
                        'consecutive_failures' => 0,
                        'last_imported_count' => 0,
                    ];
                } elseif ($lockedState->initial_watermark_at === null) {
                    // Preserve an existing installation's no-backfill boundary.
                    $updates['initial_watermark_at'] =
                        $lockedState->watermark_at;
                }

                $lockedState->update($updates);

                return [$lockedState->fresh(), $initialized, $runSequence];
            },
            3
        );

        if ($initialized) {
            return 0;
        }

        if ($state->recovery_required_at !== null) {
            $this->openOrExtendRecoveryAlarm($state, $syncUntil);

            return 0;
        }

        // A run that waited behind a newer concurrent run is already covered.
        if (
            $state->watermark_at !== null
            && $state->watermark_at->gte($syncUntil)
        ) {
            return 0;
        }

        $retentionDays = max(
            1,
            (int) config('maddraxikon.sync.recent_changes_retention_days', 30)
        );

        if ($state->watermark_at->lt($syncUntil->subDays($retentionDays))) {
            $this->openOrExtendRecoveryAlarm($state, $syncUntil);

            return 0;
        }

        $overlapMinutes = max(
            0,
            (int) config('maddraxikon.sync.overlap_minutes', 10)
        );
        $watermark = CarbonImmutable::instance($state->watermark_at);
        $maximumWindowMinutes = max(
            1,
            (int) config(
                'maddraxikon.sync.max_window_minutes',
                360
            )
        );
        $boundedUntil = $watermark->addMinutes($maximumWindowMinutes);
        $windowUntil = $boundedUntil->lt($syncUntil)
            ? $boundedUntil
            : $syncUntil;
        $syncFrom = $watermark
            ->subMinutes($overlapMinutes);

        try {
            $changes = $this->apiClient->recentChanges($syncFrom, $windowUntil);
            $importedCount = DB::transaction(function () use (
                $changes,
                $membersTeam,
                $state,
                $syncUntil,
                $windowUntil,
                $wikiKey
            ): int {
                $lockedState = MaddraxikonSyncState::query()
                    ->whereKey($state->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // A concurrent retention check must never be overwritten by a
                // normal RecentChanges run that started just before the alarm.
                if ($lockedState->recovery_required_at !== null) {
                    return 0;
                }

                // A slower run must not move the watermark backwards after a
                // newer overlapping run has already committed.
                if (
                    $lockedState->watermark_at !== null
                    && $lockedState->watermark_at->gte($windowUntil)
                ) {
                    return 0;
                }

                $lockedMembersTeam = Team::query()
                    ->whereKey($membersTeam->id)
                    ->lockForUpdate()
                    ->first();

                if (
                    ! $lockedMembersTeam
                    || $lockedMembersTeam->name !== 'Mitglieder'
                ) {
                    throw new LogicException(
                        'Das Mitglieder-Team fehlt; die Maddraxikon-Watermark bleibt unverändert.'
                    );
                }

                $eligibleUserIds = $lockedMembersTeam->activeUsers()
                    ->pluck('users.id')
                    ->all();

                $links = MaddraxikonAccountLink::query()
                    ->where('wiki_key', $wikiKey)
                    ->active()
                    ->whereIn('user_id', $eligibleUserIds)
                    ->get()
                    ->keyBy('wiki_user_id');

                $importedCount = 0;
                $lastSeenRcId = $lockedState->last_seen_rc_id;
                $touchedSessions = [];

                foreach ($changes as $change) {
                    $normalized = $this->normalizeChange($change);
                    $lastSeenRcId = max(
                        (int) ($lastSeenRcId ?? 0),
                        $normalized['rc_id']
                    );

                    if (! $this->isAllowedChange($normalized)) {
                        continue;
                    }

                    /** @var MaddraxikonAccountLink|null $link */
                    $link = $links->get($normalized['wiki_user_id']);

                    if (
                        ! $link
                        || $link->verified_at === null
                        || $normalized['occurred_at']->lt($link->verified_at)
                    ) {
                        continue;
                    }

                    $contribution = MaddraxikonContribution::query()
                        ->firstOrNew([
                            'wiki_key' => $wikiKey,
                            'revision_id' => $normalized['revision_id'],
                        ]);

                    if ($contribution->exists) {
                        continue;
                    }

                    $contribution->fill([
                        ...$normalized,
                        'account_link_id' => $link->id,
                        'user_id' => $link->user_id,
                        'status' => MaddraxikonContributionStatus::Pending,
                        'eligible_after' => $normalized['occurred_at']->addHours(
                            max(1, (int) config('maddraxikon.evaluation_delay_hours', 24))
                        ),
                    ]);
                    $contribution->save();

                    $importedCount++;

                    if ($link->wiki_username !== $normalized['wiki_username']) {
                        $link->update([
                            'wiki_username' => $normalized['wiki_username'],
                        ]);
                    }

                    if ($normalized['type'] === MaddraxikonContributionType::Edit) {
                        $key = $link->id.':'.$normalized['page_id'];
                        $touchedSessions[$key] = [
                            $link->id,
                            $normalized['page_id'],
                            CarbonImmutable::instance($link->verified_at),
                        ];
                    }
                }

                foreach ($touchedSessions as [$linkId, $pageId, $verifiedAt]) {
                    $this->rebuildPendingEditSessions(
                        (int) $linkId,
                        (int) $pageId,
                        $verifiedAt
                    );
                }

                $lockedState->update([
                    'watermark_at' => $windowUntil,
                    'last_succeeded_at' => $syncUntil,
                    'last_error_at' => null,
                    'last_error' => null,
                    'consecutive_failures' => 0,
                    'last_imported_count' => $importedCount,
                    'last_seen_rc_id' => $lastSeenRcId,
                ]);

                return $importedCount;
            }, 3);

            return $importedCount;
        } catch (Throwable $exception) {
            $this->recordFailure(
                $state,
                $exception,
                $syncUntil,
                $runSequence
            );

            throw $exception;
        }
    }

    public function recover(
        CarbonInterface $from,
        CarbonInterface $until
    ): int {
        $from = CarbonImmutable::instance($from)->startOfSecond();
        $until = CarbonImmutable::instance($until)->startOfSecond();
        $now = CarbonImmutable::now()->startOfSecond();
        $maximumDays = max(
            1,
            (int) config('maddraxikon.sync.recovery_max_window_days', 90)
        );

        if (! $from->lt($until)) {
            throw new RuntimeException(
                'Das Recovery-Fenster muss einen positiven Zeitraum umfassen.'
            );
        }

        if ($until->gt($now)) {
            throw new RuntimeException(
                'Das Recovery-Fenster darf nicht in der Zukunft enden.'
            );
        }

        if ($from->diffInSeconds($until) > $maximumDays * 86_400) {
            throw new RuntimeException(sprintf(
                'Ein Recovery-Lauf darf höchstens %d Tage umfassen.',
                $maximumDays
            ));
        }

        $wikiKey = (string) config('maddraxikon.wiki_key', 'maddraxikon-de');
        $state = MaddraxikonSyncState::query()
            ->where('wiki_key', $wikiKey)
            ->first();

        if (! $state?->watermark_at) {
            throw new RuntimeException(
                'Recovery ist erst nach dem Setzen der Go-live-Watermark möglich.'
            );
        }

        if ($state->recovery_required_at === null) {
            throw new RuntimeException(
                'Recovery ist nur für einen offenen Recovery-Alarm zulässig.'
            );
        }

        $initialWatermark = CarbonImmutable::instance(
            $state->initial_watermark_at ?? $state->watermark_at
        );

        if ($from->lt($initialWatermark)) {
            throw new RuntimeException(
                'Recovery vor der Go-live-Watermark ist nicht zulässig.'
            );
        }

        if (! $state->recovery_from_at || ! $from->equalTo(
            CarbonImmutable::instance($state->recovery_from_at)
        )) {
            throw new RuntimeException(
                'Recovery muss exakt am Anfang der offenen Lücke beginnen.'
            );
        }

        if (
            ! $state->recovery_until_at
            || $until->gt(CarbonImmutable::instance($state->recovery_until_at))
        ) {
            throw new RuntimeException(
                'Recovery darf nicht über das Ende der offenen Lücke hinausgehen.'
            );
        }

        $membersTeam = $this->membersTeamOrFail();
        $runSequence = DB::transaction(function () use (
            $now,
            $state
        ): int {
            $lockedState = MaddraxikonSyncState::query()
                ->whereKey($state->id)
                ->lockForUpdate()
                ->firstOrFail();
            $runSequence = $lockedState->run_sequence + 1;
            $lockedState->update([
                'last_started_at' => $this->storageTime($now),
                'run_sequence' => $runSequence,
            ]);

            return $runSequence;
        }, 3);

        $eligibleUserIds = $membersTeam->activeUsers()
            ->pluck('users.id')
            ->all();
        $wikiUserIds = MaddraxikonAccountLink::query()
            ->where('wiki_key', $wikiKey)
            ->active()
            ->whereIn('user_id', $eligibleUserIds)
            ->pluck('wiki_user_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        try {
            $contributions = $this->apiClient->userContributions(
                $wikiUserIds,
                $from,
                $until
            );

            return $this->persistRecoveryContributions(
                $contributions,
                $from,
                $until,
                $now,
                $state,
                $wikiKey,
                $membersTeam
            );
        } catch (Throwable $exception) {
            $this->recordFailure(
                $state,
                $exception,
                $now,
                $runSequence,
                ignoreOpenRecovery: false,
                requireOpenRecovery: true
            );
            throw $exception;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $contributions
     */
    private function persistRecoveryContributions(
        array $contributions,
        CarbonImmutable $from,
        CarbonImmutable $until,
        CarbonImmutable $now,
        MaddraxikonSyncState $state,
        string $wikiKey,
        Team $membersTeam
    ): int {
        return DB::transaction(function () use (
            $contributions,
            $from,
            $until,
            $now,
            $state,
            $wikiKey,
            $membersTeam
        ): int {
            $lockedState = MaddraxikonSyncState::query()
                ->whereKey($state->id)
                ->lockForUpdate()
                ->firstOrFail();
            $initialWatermark = CarbonImmutable::instance(
                $lockedState->initial_watermark_at
                    ?? $lockedState->watermark_at
            );

            $this->assertRecoveryWindowAgainstState(
                $lockedState,
                $from,
                $until,
                $initialWatermark
            );

            $lockedMembersTeam = Team::query()
                ->whereKey($membersTeam->id)
                ->lockForUpdate()
                ->first();

            if (
                ! $lockedMembersTeam
                || $lockedMembersTeam->name !== 'Mitglieder'
            ) {
                throw new LogicException(
                    'Das Mitglieder-Team fehlt; das Recovery-Fenster bleibt offen.'
                );
            }

            $eligibleUserIds = $lockedMembersTeam->activeUsers()
                ->pluck('users.id')
                ->all();
            $links = MaddraxikonAccountLink::query()
                ->where('wiki_key', $wikiKey)
                ->active()
                ->whereIn('user_id', $eligibleUserIds)
                ->get()
                ->keyBy('wiki_user_id');
            $importedCount = 0;

            foreach ($contributions as $rawContribution) {
                $normalized = $this->normalizeUserContribution(
                    $rawContribution
                );

                if (
                    $normalized === null
                    || ! $this->isAllowedChange($normalized)
                    || $normalized['occurred_at']->lt($from)
                    || $normalized['occurred_at']->gt($until)
                    || $normalized['occurred_at']->lt($initialWatermark)
                ) {
                    continue;
                }

                /** @var MaddraxikonAccountLink|null $link */
                $link = $links->get($normalized['wiki_user_id']);

                if (
                    ! $link
                    || $link->verified_at === null
                    || $normalized['occurred_at']->lt($link->verified_at)
                ) {
                    continue;
                }

                $contribution = MaddraxikonContribution::query()
                    ->firstOrNew([
                        'wiki_key' => $wikiKey,
                        'revision_id' => $normalized['revision_id'],
                    ]);

                if ($contribution->exists) {
                    continue;
                }

                $contribution->fill([
                    ...$normalized,
                    'account_link_id' => $link->id,
                    'user_id' => $link->user_id,
                    // usercontribs does not retain the per-edit RC bot marker.
                    // Recovered rows are therefore audit-only and fail closed.
                    'status' => MaddraxikonContributionStatus::Rejected,
                    'status_reason' => 'recovery_bot_status_unverifiable',
                    'eligible_after' => $normalized['occurred_at']->addHours(
                        max(1, (int) config('maddraxikon.evaluation_delay_hours', 24))
                    ),
                    'checked_at' => $now,
                ]);
                $contribution->save();
                $importedCount++;

                if ($link->wiki_username !== $normalized['wiki_username']) {
                    $link->update([
                        'wiki_username' => $normalized['wiki_username'],
                    ]);
                }
            }

            $this->finishRecoveryState(
                $lockedState,
                $from,
                $until,
                $now,
                $importedCount
            );

            return $importedCount;
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $contribution
     * @return array<string, mixed>|null
     */
    private function normalizeUserContribution(array $contribution): ?array
    {
        if (
            array_key_exists('userhidden', $contribution)
            || array_key_exists('suppressed', $contribution)
        ) {
            return null;
        }

        return $this->normalizeVisibleUserContribution($contribution);
    }

    /**
     * @param  array<string, mixed>  $contribution
     * @return array<string, mixed>
     */
    private function normalizeVisibleUserContribution(array $contribution): array
    {
        $this->validateUserContribution($contribution);

        try {
            $occurredAt = CarbonImmutable::parse(
                (string) $contribution['timestamp']
            )->utc();
        } catch (Throwable) {
            throw new MaddraxikonApiException(
                'UserContribs-Eintrag mit ungültigem Zeitstempel.'
            );
        }

        return $this->buildUserContributionFields(
            $contribution,
            $occurredAt
        );
    }

    /** @param array<string, mixed> $contribution */
    private function validateUserContribution(array $contribution): void
    {
        foreach (['revid', 'pageid', 'ns', 'userid'] as $key) {
            if (
                ! isset($contribution[$key])
                || (int) $contribution[$key] < ($key === 'ns' ? 0 : 1)
            ) {
                throw new MaddraxikonApiException(
                    "UserContribs-Eintrag ohne gültiges Feld {$key}."
                );
            }
        }

        if (! isset(
            $contribution['timestamp'],
            $contribution['title'],
            $contribution['user']
        )) {
            throw new MaddraxikonApiException(
                'UserContribs-Eintrag mit unvollständigen Kerndaten.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $contribution
     * @return array<string, mixed>
     */
    private function buildUserContributionFields(
        array $contribution,
        CarbonImmutable $occurredAt
    ): array {
        $parentRevisionId = isset($contribution['parentid'])
            ? (int) $contribution['parentid']
            : null;
        $newSize = isset($contribution['size'])
            ? max(0, (int) $contribution['size'])
            : null;
        $oldSize = null;

        if ($parentRevisionId === 0) {
            $oldSize = 0;
        } elseif ($newSize !== null && isset($contribution['sizediff'])) {
            $oldSize = max(
                0,
                $newSize - (int) $contribution['sizediff']
            );
        }

        return [
            'rc_id' => null,
            'revision_id' => (int) $contribution['revid'],
            'parent_revision_id' => $parentRevisionId,
            'page_id' => (int) $contribution['pageid'],
            'namespace_id' => (int) $contribution['ns'],
            'page_title' => (string) $contribution['title'],
            'wiki_user_id' => (int) $contribution['userid'],
            'wiki_username' => (string) $contribution['user'],
            'type' => array_key_exists('new', $contribution)
                || $parentRevisionId === 0
                    ? MaddraxikonContributionType::New
                    : MaddraxikonContributionType::Edit,
            'minor' => array_key_exists('minor', $contribution),
            'bot' => array_key_exists('bot', $contribution),
            'anonymous' => false,
            'redirect' => array_key_exists('redirect', $contribution),
            'user_hidden' => false,
            'old_size' => $oldSize,
            'new_size' => $newSize,
            'tags' => collect($contribution['tags'] ?? [])
                ->filter(static fn (mixed $tag): bool => is_string($tag))
                ->values()
                ->all(),
            'occurred_at' => $occurredAt,
        ];
    }

    private function assertRecoveryWindowAgainstState(
        MaddraxikonSyncState $state,
        CarbonImmutable $from,
        CarbonImmutable $until,
        CarbonImmutable $initialWatermark
    ): void {
        if ($from->lt($initialWatermark)) {
            throw new RuntimeException(
                'Recovery vor der Go-live-Watermark ist nicht zulässig.'
            );
        }

        if ($state->recovery_required_at === null) {
            throw new RuntimeException(
                'Eine parallele Änderung hat den Recovery-Alarm geschlossen.'
            );
        }

        if (! $state->recovery_from_at || ! $from->equalTo(
            CarbonImmutable::instance($state->recovery_from_at)
        )) {
            throw new RuntimeException(
                'Eine parallele Recovery hat den Anfang der offenen Lücke verändert.'
            );
        }

        if (
            ! $state->recovery_until_at
            || $until->gt(CarbonImmutable::instance(
                $state->recovery_until_at
            ))
        ) {
            throw new RuntimeException(
                'Das Recovery-Fenster reicht über die offene Lücke hinaus.'
            );
        }
    }

    private function finishRecoveryState(
        MaddraxikonSyncState $state,
        CarbonImmutable $from,
        CarbonImmutable $until,
        CarbonImmutable $now,
        int $importedCount
    ): void {
        $storedNow = $this->storageTime($now);
        $updates = [
            'last_succeeded_at' => $storedNow,
            'last_imported_count' => $importedCount,
            'last_recovery_succeeded_at' => $storedNow,
            'last_recovered_from_at' => $this->storageTime($from),
            'last_recovered_until_at' => $this->storageTime($until),
            'last_recovered_count' => $importedCount,
        ];

        if ($state->recovery_required_at !== null) {
            $openUntil = $state->recovery_until_at
                ? CarbonImmutable::instance($state->recovery_until_at)
                : $until;
            $recoveryComplete = ! $until->lt($openUntil);
            $updates['watermark_at'] = $this->storageTime($until);

            if ($recoveryComplete) {
                $updates = [
                    ...$updates,
                    'recovery_required_at' => null,
                    'recovery_from_at' => null,
                    'recovery_until_at' => null,
                    'last_error_at' => null,
                    'last_error' => null,
                    'consecutive_failures' => 0,
                ];
            } else {
                $updates = [
                    ...$updates,
                    'recovery_from_at' => $this->storageTime($until),
                    'last_error_at' => $storedNow,
                    'last_error' => $this->recoveryAlarmMessage(
                        $until,
                        $openUntil
                    ),
                ];
            }
        }

        $state->update($updates);
    }

    private function openOrExtendRecoveryAlarm(
        MaddraxikonSyncState $state,
        CarbonImmutable $until
    ): void {
        $opened = false;
        $window = DB::transaction(function () use (
            $state,
            $until,
            &$opened
        ): array {
            $lockedState = MaddraxikonSyncState::query()
                ->whereKey($state->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedState->recovery_required_at === null) {
                $retentionDays = max(
                    1,
                    (int) config(
                        'maddraxikon.sync.recent_changes_retention_days',
                        30
                    )
                );

                if (
                    ! $lockedState->watermark_at
                    || ! $lockedState->watermark_at->lt(
                        $until->subDays($retentionDays)
                    )
                ) {
                    return [];
                }

                $opened = true;
            }

            $from = CarbonImmutable::instance(
                $lockedState->recovery_from_at
                    ?? $lockedState->watermark_at
            );
            $previousUntil = $lockedState->recovery_until_at
                ? CarbonImmutable::instance($lockedState->recovery_until_at)
                : null;
            $openUntil = $previousUntil && $previousUntil->gt($until)
                ? $previousUntil
                : $until;
            $updates = [
                'recovery_required_at' => $lockedState->recovery_required_at
                    ?? $this->storageTime($until),
                'recovery_from_at' => $this->storageTime($from),
                'recovery_until_at' => $this->storageTime($openUntil),
                'last_error_at' => $this->storageTime($until),
                'last_error' => $this->recoveryAlarmMessage(
                    $from,
                    $openUntil
                ),
                'last_imported_count' => 0,
            ];

            $lockedState->update($updates);

            if ($opened) {
                MaddraxikonSyncState::query()
                    ->whereKey($lockedState->id)
                    ->increment('consecutive_failures');
            }

            return [$from, $openUntil];
        }, 3);

        if ($opened && $window !== []) {
            Log::critical('Maddraxikon-Recovery erforderlich.', [
                'wiki_key' => $state->wiki_key,
                'from' => $window[0]->utc()->toIso8601String(),
                'until' => $window[1]->utc()->toIso8601String(),
            ]);
        }
    }

    private function recoveryAlarmMessage(
        CarbonInterface $from,
        CarbonInterface $until
    ): string {
        return sprintf(
            'RecentChanges-Retention überschritten; Recovery von %s bis %s erforderlich. Die Watermark wird nicht übersprungen.',
            $from->clone()->utc()->toIso8601String(),
            $until->clone()->utc()->toIso8601String()
        );
    }

    private function storageTime(CarbonInterface $timestamp): CarbonImmutable
    {
        return CarbonImmutable::instance($timestamp)->utc();
    }

    /**
     * @param  array<string, mixed>  $change
     * @return array{
     *     rc_id: int,
     *     revision_id: int,
     *     parent_revision_id: ?int,
     *     page_id: int,
     *     namespace_id: int,
     *     page_title: string,
     *     wiki_user_id: int,
     *     wiki_username: string,
     *     type: MaddraxikonContributionType,
     *     minor: bool,
     *     bot: bool,
     *     anonymous: bool,
     *     redirect: bool,
     *     user_hidden: bool,
     *     old_size: ?int,
     *     new_size: ?int,
     *     tags: list<string>,
     *     occurred_at: CarbonImmutable
     * }
     */
    private function normalizeChange(array $change): array
    {
        $requiredIntegerKeys = ['rcid', 'revid', 'pageid', 'ns'];

        foreach ($requiredIntegerKeys as $key) {
            if (
                ! isset($change[$key])
                || (int) $change[$key] < ($key === 'ns' ? 0 : 1)
            ) {
                throw new MaddraxikonApiException(
                    "RecentChanges-Eintrag ohne gültiges Feld {$key}."
                );
            }
        }

        if (
            ! isset($change['timestamp'], $change['title'], $change['type'])
            || ! in_array($change['type'], ['edit', 'new'], true)
        ) {
            throw new MaddraxikonApiException(
                'RecentChanges-Eintrag mit unvollständigen Kerndaten.'
            );
        }

        try {
            $occurredAt = CarbonImmutable::parse((string) $change['timestamp'])
                ->utc();
        } catch (Throwable) {
            throw new MaddraxikonApiException(
                'RecentChanges-Eintrag mit ungültigem Zeitstempel.'
            );
        }

        return [
            'rc_id' => (int) $change['rcid'],
            'revision_id' => (int) $change['revid'],
            'parent_revision_id' => isset($change['old_revid'])
                ? (int) $change['old_revid']
                : null,
            'page_id' => (int) $change['pageid'],
            'namespace_id' => (int) $change['ns'],
            'page_title' => (string) $change['title'],
            'wiki_user_id' => (int) ($change['userid'] ?? 0),
            'wiki_username' => (string) ($change['user'] ?? ''),
            'type' => MaddraxikonContributionType::from((string) $change['type']),
            'minor' => array_key_exists('minor', $change),
            'bot' => array_key_exists('bot', $change),
            'anonymous' => array_key_exists('anon', $change),
            'redirect' => array_key_exists('redirect', $change),
            'user_hidden' => array_key_exists('userhidden', $change),
            'old_size' => isset($change['oldlen']) ? (int) $change['oldlen'] : null,
            'new_size' => isset($change['newlen']) ? (int) $change['newlen'] : null,
            'tags' => collect($change['tags'] ?? [])
                ->filter(static fn (mixed $tag): bool => is_string($tag))
                ->values()
                ->all(),
            'occurred_at' => $occurredAt,
        ];
    }

    /**
     * @param  array{
     *     revision_id: int,
     *     page_id: int,
     *     namespace_id: int,
     *     wiki_user_id: int,
     *     wiki_username: string,
     *     type: MaddraxikonContributionType,
     *     bot: bool,
     *     anonymous: bool,
     *     user_hidden: bool
     * }  $change
     */
    private function isAllowedChange(array $change): bool
    {
        if (
            $change['revision_id'] < 1
            || $change['page_id'] < 1
            || $change['wiki_user_id'] < 1
            || trim($change['wiki_username']) === ''
            || $change['bot']
            || $change['anonymous']
            || $change['user_hidden']
        ) {
            return false;
        }

        return in_array(
            $change['namespace_id'],
            collect(config(
                'maddraxikon.allowed_namespaces',
                [0, 10, 14, 102, 106, 108, 112, 420]
            ))->map(static fn (mixed $id): int => (int) $id)->all(),
            true
        );
    }

    private function rebuildPendingEditSessions(
        int $linkId,
        int $pageId,
        CarbonImmutable $verifiedAt
    ): void {
        $contributions = MaddraxikonContribution::query()
            ->where('account_link_id', $linkId)
            ->where('page_id', $pageId)
            ->where('type', MaddraxikonContributionType::Edit)
            ->where('status', MaddraxikonContributionStatus::Pending)
            ->where(function ($query) use ($verifiedAt): void {
                $query
                    ->where(
                        'occurred_at_epoch',
                        '>=',
                        $verifiedAt->getTimestamp()
                    )
                    ->orWhere(function ($query) use ($verifiedAt): void {
                        $query
                            ->whereNull('occurred_at_epoch')
                            ->where('occurred_at', '>=', $verifiedAt);
                    });
            })
            ->orderBy('occurred_at_epoch')
            ->orderBy('occurred_at')
            ->orderBy('revision_id')
            ->lockForUpdate()
            ->get();

        if ($contributions->isEmpty()) {
            return;
        }

        $windowSeconds = max(
            60,
            (int) config('maddraxikon.session_window_minutes', 30) * 60
        );
        $delayHours = max(
            1,
            (int) config('maddraxikon.evaluation_delay_hours', 24)
        );
        $sessions = [];
        $current = [];
        $previousAt = null;

        foreach ($contributions as $contribution) {
            $occurredAt = $contribution->occurredAtUtc();

            if (
                $current !== []
                && $previousAt instanceof CarbonImmutable
                && $previousAt->diffInSeconds($occurredAt) > $windowSeconds
            ) {
                $sessions[] = $current;
                $current = [];
            }

            $current[] = $contribution;
            $previousAt = $occurredAt;
        }

        if ($current !== []) {
            $sessions[] = $current;
        }

        foreach ($sessions as $session) {
            /** @var MaddraxikonContribution $first */
            $first = $session[0];
            /** @var MaddraxikonContribution $last */
            $last = $session[array_key_last($session)];
            $eligibleAfter = $last->occurredAtUtc()->addHours($delayHours);

            foreach ($session as $contribution) {
                $contribution->update([
                    'session_anchor_revision_id' => $first->revision_id,
                    'eligible_after' => $eligibleAfter,
                ]);
            }
        }
    }

    private function recordFailure(
        MaddraxikonSyncState $state,
        Throwable $exception,
        CarbonImmutable $startedAt,
        int $runSequence,
        bool $ignoreOpenRecovery = true,
        bool $requireOpenRecovery = false
    ): void {
        $message = mb_substr($exception->getMessage(), 0, 2000);

        DB::transaction(function () use (
            $ignoreOpenRecovery,
            $message,
            $state,
            $runSequence,
            $requireOpenRecovery
        ): void {
            $lockedState = MaddraxikonSyncState::query()
                ->whereKey($state->id)
                ->lockForUpdate()
                ->first();

            // Do not turn a newer successful run into a false incident when an
            // older overlapping API request fails afterwards.
            if (
                ! $lockedState
                || $lockedState->run_sequence !== $runSequence
                || (
                    $ignoreOpenRecovery
                    && $lockedState->recovery_required_at !== null
                )
                || (
                    $requireOpenRecovery
                    && $lockedState->recovery_required_at === null
                )
            ) {
                return;
            }

            $lockedState->update([
                'last_error_at' => now(),
                'last_error' => $message,
                'consecutive_failures' => $lockedState->consecutive_failures + 1,
            ]);
        }, 3);

        Log::error('Maddraxikon-Synchronisation fehlgeschlagen.', [
            'wiki_key' => $state->wiki_key,
            'started_at' => $startedAt->utc()->toIso8601String(),
            'exception' => $exception::class,
            'message' => $message,
        ]);
    }

    private function membersTeamOrFail(): Team
    {
        $membersTeam = Team::membersTeam();

        if (! $membersTeam) {
            throw new LogicException(
                'Das Mitglieder-Team fehlt; der Maddraxikon-Import wurde sicher abgebrochen.'
            );
        }

        return $membersTeam;
    }
}
