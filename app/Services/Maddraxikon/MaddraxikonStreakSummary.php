<?php

namespace App\Services\Maddraxikon;

use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonContribution;
use App\Models\MaddraxikonStreakDay;
use App\Models\MaddraxikonStreakRun;
use App\Models\MaddraxikonStreakState;
use App\Models\MaddraxikonSyncState;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class MaddraxikonStreakSummary
{
    public function __construct(private readonly MaddraxikonStreakSettings $settings, private readonly AccountEligibility $eligibility) {}

    public function forUser(User $user): ?array
    {
        if (! $this->settings->enabled() || $this->settings->start()->isFuture() || ! $this->eligibility->isEligible($user)) {
            return null;
        }
        $wiki = (string) config('maddraxikon.wiki_key');
        $today = CarbonImmutable::now($this->settings->timezone())->startOfDay();
        $link = MaddraxikonAccountLink::query()->where('user_id', $user->id)->where('wiki_key', $wiki)->first();
        $query = MaddraxikonStreakDay::query()->where('wiki_key', $wiki)->where('user_id', $user->id);
        $days = (clone $query)->where('activity_date', '>=', $today->subDays(30)->toDateString())->get()->keyBy(fn ($day) => $day->activity_date->toDateString());
        $latest = (clone $query)->where('streak_length', '>', 0)->orderByDesc('activity_date')->first();
        $sync = MaddraxikonSyncState::query()->where('wiki_key', $wiki)->first();
        $run = MaddraxikonStreakRun::query()->where('wiki_key', $wiki)->first();
        $dirty = MaddraxikonStreakState::query()->where('wiki_key', $wiki)->where('user_id', $user->id)->first();
        $sources = MaddraxikonContribution::query()->where('wiki_key', $wiki)->where('user_id', $user->id)
            ->where('occurred_at_epoch', '>=', $this->settings->start()->timestamp)
            ->where('streak_activity_date', '>=', $today->subDays(30)->toDateString())
            ->select('streak_activity_date', 'streak_status', DB::raw('COUNT(*) AS total'))
            ->groupBy('streak_activity_date', 'streak_status')->get()->groupBy('streak_activity_date');
        $delayed = ! $sync?->last_succeeded_at || $sync->last_succeeded_at->lt(now()->subMinutes((int) config('maddraxikon.monitoring.import_stale_minutes', 60)))
            || ! $sync->watermark_at || $sync->watermark_at->lt(now()->subMinutes((int) config('maddraxikon.monitoring.import_stale_minutes', 60)))
            || $sync->recovery_required_at !== null || ! $sync->initial_watermark_at || $sync->initial_watermark_at->gt($this->settings->start())
            || $run?->last_error !== null;
        $timeline = [];
        for ($offset = 30; $offset >= 0; $offset--) {
            $date = $today->subDays($offset);
            $key = $date->toDateString();
            $day = $days->get($key);
            $pending = ($sources->get($key) ?? collect())->where('streak_status', 'pending')->sum('total');
            $status = $day?->streak_length > 0 ? 'confirmed' : ($pending > 0 || $day?->status === 'valid' ? 'pending' : 'empty');
            if ($status === 'empty' && ($delayed || $offset === 0)) {
                $status = 'unknown';
            }
            $timeline[] = ['date' => $key, 'label' => $date->locale('de')->isoFormat('dd'), 'full' => $date->locale('de')->isoFormat('dddd, D. MMMM'), 'status' => $status, 'points' => $day?->settled_points ?? 0];
        }
        $hasPending = collect($timeline)->contains('status', 'pending');
        $recalculating = $dirty?->dirty_from && $latest && $dirty->dirty_from <= $latest->activity_date->toDateString();
        $knownGap = $latest && collect($timeline)->contains(fn ($day) => $day['date'] > $latest->activity_date->toDateString() && $day['date'] < $today->toDateString() && $day['status'] === 'empty');
        $current = $latest && ! $knownGap && ($latest->activity_date->toDateString() >= $today->subDay()->toDateString() || $delayed || $hasPending || $recalculating) ? $latest->streak_length : 0;
        $todayStatus = $timeline[30]['status'];
        $todayPending = ($sources->get($today->toDateString()) ?? collect())->where('streak_status', 'pending')->sum('total') > 0;
        $next = $delayed || $recalculating || ($hasPending && ! $todayPending) ? null : $current + 1;

        return [
            'linked' => $link?->isActive() ?? false, 'linking_enabled' => (bool) config('maddraxikon.features.linking_enabled'),
            'paused' => ! config('maddraxikon.features.awards_enabled') || ! config('maddraxikon.features.sync_enabled'),
            'current' => $current, 'record' => (int) (clone $query)->max('streak_length'),
            'total' => (int) (clone $query)->sum('settled_points'), 'next' => $next,
            'pending' => $hasPending, 'today_pending' => $todayPending, 'today_status' => $todayStatus,
            'delayed' => $delayed, 'recalculating' => (bool) $recalculating,
            'last_sync' => $sync?->last_succeeded_at?->setTimezone($this->settings->timezone())->format('d.m.Y. H:i'),
            'last_confirmed' => $latest?->activity_date->format('d.m.Y.'),
            'week' => array_slice($timeline, -7), 'month' => $timeline,
            'delay_hours' => max(1, (int) config('maddraxikon.evaluation_delay_hours', 24)),
        ];
    }
}
