<?php

namespace App\Services\Maddraxikon;

use App\Enums\Role;
use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonContribution;
use App\Models\MaddraxikonStreakBooking;
use App\Models\MaddraxikonStreakDay;
use App\Models\MaddraxikonStreakRun;
use App\Models\MaddraxikonStreakState;
use App\Models\MaddraxikonSyncState;
use App\Models\User;
use App\Services\Baxx\BaxxBookingWriter;
use App\Services\LockedMembersTeamMemberships;
use App\Services\MembersTeamMembershipLock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

final class MaddraxikonStreakService
{
    public function __construct(
        private readonly MaddraxikonApiClient $api,
        private readonly MaddraxikonStreakSettings $settings,
        private readonly MaddraxikonStreakTracker $tracker,
        private readonly MaddraxikonStreakContributionValidator $validator,
        private readonly MaddraxikonStreakCalculator $calculator,
        private readonly MembersTeamMembershipLock $memberships,
        private readonly BaxxBookingWriter $writer,
    ) {}

    public function run(bool $recheck = false): int
    {
        $run = $this->settings->activate();
        if (! $run || ! config('maddraxikon.features.awards_enabled') || ! config('maddraxikon.features.sync_enabled')) {
            return 0;
        }
        $sync = $this->sync();
        if (! $this->covered($sync)) {
            return 0;
        }
        $limit = (int) config('maddraxikon.streaks.batch_size');
        try {
            // Also discovers imports made during a disabled worker or before deployment.
            $untracked = $this->sources()->whereNull('streak_activity_date')->orderBy('id')->limit($limit)->get();
            foreach ($untracked as $source) {
                $this->locked($source->user_id, function () use ($source): void {
                    $fresh = $source->fresh();
                    $link = $fresh->accountLink;
                    if ($link?->isActive() && $fresh->streak_activity_date === null) {
                        $this->tracker->record($fresh, $link);
                    }
                });
            }
            $query = $this->sources(historical: $recheck)->whereNotNull('streak_activity_date')
                ->where('occurred_at_epoch', '<=', now()->timestamp - max(1, (int) config('maddraxikon.evaluation_delay_hours', 24)) * 3600)
                ->where('streak_next_check_epoch', '<=', now()->timestamp)
                ->where('streak_status', '!=', 'excluded');
            $recheck ? $query->whereNotNull('streak_checked_at_epoch') : $query->whereNull('streak_checked_at_epoch');
            $sources = $query->orderBy('streak_next_check_epoch')->orderBy('id')->limit($limit)->get();
            if ($sources->isNotEmpty()) {
                $revisions = $this->api->streakRevisionDetails($sources->pluck('revision_id')->all());
                $pages = $this->api->streakPageDetails($sources->pluck('page_id')->unique()->all());
                foreach ($sources as $source) {
                    if (! isset($revisions[$source->revision_id], $pages[$source->page_id])) {
                        throw new LogicException('Die Serienprüfung ist unvollständig; bestehende Ansprüche bleiben erhalten.');
                    }
                    $reason = $this->validator->reason($source, $revisions[$source->revision_id], $pages[$source->page_id]);
                    $this->locked($source->user_id, function (LockedMembersTeamMemberships $members) use ($source, $reason, $recheck): void {
                        $fresh = MaddraxikonContribution::query()->lockForUpdate()->findOrFail($source->id);
                        $link = $fresh->accountLink;
                        // Previously checked sources retain their original participation and identity snapshot.
                        // Current eligibility is required for new checks; reconcile gates all positive payouts.
                        if ($fresh->streak_check_version !== $source->streak_check_version || (! $recheck && ! $this->eligible($fresh, $link, $members))) {
                            return;
                        }
                        $status = $reason === null ? 'valid' : 'invalid';
                        if ($fresh->status_reason === 'recovery_bot_status_unverifiable' && ! $fresh->streak_bot_verified && $reason === null) {
                            $status = 'pending';
                            $reason = 'recovery_bot_status_unverifiable';
                        }
                        $statusChanged = $fresh->streak_status !== $status;
                        $fresh->forceFill([
                            'streak_status' => $status,
                            'streak_reason' => $reason,
                            'streak_check_version' => $fresh->streak_check_version + 1,
                            'streak_checked_at_epoch' => now()->timestamp,
                            'streak_next_check_epoch' => now()->timestamp + (int) config('maddraxikon.streaks.recheck_hours') * 3600,
                        ])->save();
                        if ($statusChanged) {
                            $this->tracker->dirty($fresh->wiki_key, $fresh->user_id, $fresh->streak_activity_date);
                        }
                    });
                }
            }
            $bookings = 0;
            $states = MaddraxikonStreakState::query()->where('wiki_key', config('maddraxikon.wiki_key'))
                ->whereNotNull('dirty_from')->orderBy('updated_at')->orderBy('id')->limit($limit)->get();
            foreach ($states as $state) {
                $bookings += $this->reconcile($state->user_id);
            }
            $run->update([$recheck ? 'last_rechecked_epoch' : 'last_evaluated_epoch' => now()->timestamp, 'last_error' => null]);

            return $bookings;
        } catch (Throwable $exception) {
            $run->update(['last_error' => mb_substr($exception->getMessage(), 0, 1000)]);
            throw $exception;
        }
    }

    private function sources(bool $historical = false): Builder
    {
        $query = MaddraxikonContribution::query()->where('wiki_key', config('maddraxikon.wiki_key'))
            ->where('occurred_at_epoch', '>=', $this->settings->start()->timestamp);

        // Historical validity must remain checkable after disconnects or membership changes.
        return $historical ? $query : $query
            ->whereHas('accountLink', fn (Builder $query) => $query->active())
            ->whereHas('user', fn (Builder $query) => $query->whereHas('teams', fn (Builder $teams) => $teams->where('name', 'Mitglieder')->where('team_user.role', '!=', Role::Anwaerter->value)));
    }

    private function eligible(MaddraxikonContribution $source, ?MaddraxikonAccountLink $link, LockedMembersTeamMemberships $members): bool
    {
        return $link?->isActive() && $link->wiki_key === $source->wiki_key
            && $link->wiki_user_id === $source->wiki_user_id && $link->user_id === $source->user_id
            && $members->isActiveMember($source->user_id)
            && $source->streak_verified_at_epoch !== null
            && $source->occurredAtUtc()->timestamp >= $source->streak_verified_at_epoch;
    }

    public function reconcile(int $userId, bool $pretend = false, ?string $from = null): int
    {
        if (! $this->settings->enabled() || ! config('maddraxikon.features.awards_enabled') || ! config('maddraxikon.features.sync_enabled')) {
            return 0;
        }

        $pinned = MaddraxikonStreakRun::query()->where('wiki_key', config('maddraxikon.wiki_key'))->first();
        if ($pinned && $pinned->starts_at_epoch !== $this->settings->start()->timestamp) {
            throw new LogicException('Der bereits verwendete Serienstart darf nicht verschoben werden.');
        }

        return $this->locked($userId, function (LockedMembersTeamMemberships $members) use ($userId, $pretend, $from): int {
            $sync = $this->sync();
            $state = MaddraxikonStreakState::query()->where('wiki_key', config('maddraxikon.wiki_key'))->where('user_id', $userId)->lockForUpdate()->first();
            if ($state && $from !== null) {
                $state->dirty_from = $state->dirty_from === null ? $from : min($state->dirty_from, $from);
            }
            if (! $this->covered($sync) || ! $state?->dirty_from) {
                return 0;
            }
            // Untracked sources make the affected history incomplete.
            if (MaddraxikonContribution::query()->where('wiki_key', $state->wiki_key)->where('user_id', $userId)
                ->where('occurred_at_epoch', '>=', $this->settings->start()->timestamp)->whereNull('streak_activity_date')->exists()) {
                return 0;
            }
            $days = MaddraxikonStreakDay::query()->where('wiki_key', $state->wiki_key)->where('user_id', $userId)
                ->where('activity_date', '>=', $state->dirty_from)->orderBy('activity_date')->limit((int) config('maddraxikon.streaks.batch_size'))->lockForUpdate()->get();
            $previous = MaddraxikonStreakDay::query()->where('wiki_key', $state->wiki_key)->where('user_id', $userId)
                ->where('activity_date', '<', $state->dirty_from)->orderByDesc('activity_date')->first();
            $link = MaddraxikonAccountLink::query()->where('wiki_key', $state->wiki_key)->where('user_id', $userId)->first();
            $canAward = $link?->isActive() && $members->isActiveMember($userId);
            $blockedFrom = $state->pending_from;
            $bookings = 0;
            foreach ($days as $day) {
                $date = $day->activity_date->toDateString();
                $sources = MaddraxikonContribution::query()->where('wiki_key', $state->wiki_key)->where('user_id', $userId)
                    ->where('streak_activity_date', $date)->get();
                $valid = $sources->where('streak_status', 'valid')->count();
                $pending = $sources->where('streak_status', 'pending')->count();
                $end = CarbonImmutable::parse($date, $this->settings->timezone())->addDay()->utc();
                $dayStatus = $valid > 0 ? 'valid' : (($pending > 0 || $sync->watermark_at->lt($end)) ? 'pending' : 'inactive');
                $previousLength = $previous?->streak_length ?? ($previous ? null : 0);
                if ($previous && $previous->activity_date->addDay()->toDateString() < $date) {
                    $previousLength = $sync->watermark_at->gte(CarbonImmutable::parse($date, $this->settings->timezone())->utc()) ? 0 : null;
                }
                $length = $this->calculator->length($dayStatus, $previousLength);
                $delta = $length === null ? 0 : $length - $day->settled_points;
                if ($length === null || ($delta > 0 && ! $canAward)) {
                    $blockedFrom ??= $date;
                }
                $day->fill(['status' => $dayStatus, 'valid_count' => $valid, 'pending_count' => $pending, 'streak_length' => $length, 'target_points' => $length]);
                if ($delta !== 0 && ($delta < 0 || $canAward)) {
                    $bookings++;
                    if (! $pretend) {
                        $point = $this->writer->record($userId, $members->team->id, $delta);
                        $version = $day->calculation_version + 1;
                        MaddraxikonStreakBooking::query()->create([
                            'streak_day_id' => $day->id, 'user_id' => $userId, 'team_id' => $members->team->id,
                            'user_point_id' => $point->id, 'points_delta' => $delta, 'previous_points' => $day->settled_points,
                            'target_points' => $length, 'calculation_version' => $version,
                            'reason' => $version === 1 ? 'daily_bonus' : 'series_recalculated',
                            'source_revision_ids' => $sources->pluck('revision_id')->all(),
                        ]);
                        $day->fill(['settled_points' => $length, 'calculation_version' => $version]);
                    }
                }
                if (! $pretend) {
                    $day->save();
                }
                $previous = $day;
            }
            if (! $pretend) {
                $next = $days->isEmpty() ? null : MaddraxikonStreakDay::query()->where('wiki_key', $state->wiki_key)->where('user_id', $userId)
                    ->where('activity_date', '>', $days->last()->activity_date->toDateString())->min('activity_date');
                $state->update(['dirty_from' => $next ?? $blockedFrom, 'pending_from' => $next ? $blockedFrom : null]);
            }

            return $bookings;
        }) ?? 0;
    }

    public function verifyRecoveredSource(int $sourceId, User $admin, string $reason): void
    {
        if (mb_strlen(trim($reason)) < 10) {
            throw new LogicException('Bitte den Nachweis mit mindestens zehn Zeichen begründen.');
        }
        $source = MaddraxikonContribution::query()->findOrFail($sourceId);
        DB::transaction(function () use ($source, $admin, $reason): void {
            MaddraxikonSyncState::query()->where('wiki_key', $source->wiki_key)->lockForUpdate()->first();
            $this->memberships->run([$admin->id, $source->user_id], function (LockedMembersTeamMemberships $members) use ($source, $admin, $reason): void {
                abort_unless($members->hasRole($admin->id, Role::Admin), 403);
                $fresh = MaddraxikonContribution::query()->lockForUpdate()->findOrFail($source->id);
                if ($fresh->wiki_key !== config('maddraxikon.wiki_key') || $fresh->status_reason !== 'recovery_bot_status_unverifiable') {
                    throw new LogicException('Dieser Beitrag benötigt keine Recovery-Bestätigung.');
                }
                $fresh->forceFill([
                    'streak_bot_verified' => true, 'streak_reviewed_by' => $admin->id,
                    'streak_review_reason' => trim($reason), 'streak_next_check_epoch' => 0,
                    'streak_check_version' => $fresh->streak_check_version + 1,
                ])->save();
            });
        }, 3);
    }

    private function sync(): ?MaddraxikonSyncState
    {
        return MaddraxikonSyncState::query()->where('wiki_key', config('maddraxikon.wiki_key'))->first();
    }

    public function covered(?MaddraxikonSyncState $sync): bool
    {
        $start = $this->settings->start();

        return $start && $sync?->watermark_at && $sync->initial_watermark_at
            && $sync->initial_watermark_at->lte($start) && $sync->recovery_required_at === null;
    }

    private function locked(int $userId, callable $callback): mixed
    {
        return DB::transaction(function () use ($userId, $callback) {
            $sync = MaddraxikonSyncState::query()->where('wiki_key', config('maddraxikon.wiki_key'))->lockForUpdate()->first();
            if (! $this->covered($sync)) {
                return null;
            }

            return $this->memberships->run([$userId], $callback);
        }, 3);
    }
}
