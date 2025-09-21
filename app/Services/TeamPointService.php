<?php

namespace App\Services;

use App\Models\Team;
use App\Models\User;
use App\Models\UserPoint;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class TeamPointService
{

    /**
     * Get the total points of a user in their current team.
     */
    public function getUserPoints(User $user): int
    {
        $team = $user->currentTeam;

        return $team ? $user->totalPointsForTeam($team) : 0;
    }

    /**
     * Build the metrics needed for the todo dashboard.
     *
     * @return array{
     *     user_points:int,
     *     trend:array<int, array{date:string,label:string,points:int}>,
     *     trend_max:int,
     *     weekly:array{total:int,target:int,progress:int},
     *     team_average:float,
     *     team_average_progress:int,
     *     team_average_ratio:float|null,
     *     leaderboard:array<int, array{rank:int|null,name:string,points:int,is_current_user:bool,is_additional?:bool}>,
     *     user_rank:int|null,
     *     points_to_next_rank:int|null,
     *     next_rank_points:int|null
     * }
     */
    public function getDashboardMetrics(User $user, Team $team): array
    {
        $userPoints = $this->getUserPoints($user);
        $trend = $this->getUserPointTrend($user, $team);
        $trendMax = collect($trend)->max(fn (array $entry) => $entry['points']) ?? 0;
        $weeklyTotal = array_sum(array_column($trend, 'points'));
        $weeklyTarget = $this->calculateWeeklyTarget($user, $team);

        $leaderboardTotals = $this->getLeaderboardTotals($team);
        $teamAverage = $this->calculateTeamAverage($team, $leaderboardTotals);
        $rankData = $this->resolveUserRankData($user, $leaderboardTotals);
        $leaderboard = $this->buildLeaderboard($leaderboardTotals, $user, $team);

        $teamAverageRatio = $teamAverage > 0
            ? round(($userPoints / $teamAverage) * 100, 1)
            : null;

        return [
            'user_points' => $userPoints,
            'trend' => $trend,
            'trend_max' => $trendMax,
            'weekly' => [
                'total' => $weeklyTotal,
                'target' => $weeklyTarget,
                'progress' => $this->calculateProgress($weeklyTotal, $weeklyTarget),
            ],
            'team_average' => $teamAverage,
            'team_average_progress' => $teamAverage > 0
                ? $this->calculateProgress($userPoints, $teamAverage)
                : 0,
            'team_average_ratio' => $teamAverageRatio,
            'leaderboard' => $leaderboard,
            'user_rank' => $rankData['rank'],
            'points_to_next_rank' => $rankData['points_to_next_rank'],
            'next_rank_points' => $rankData['next_rank_points'],
        ];
    }

    /**
     * Retrieve the last X days of user points as an ordered trend.
     *
     * @return array<int, array{date:string,label:string,points:int}>
     */
    public function getUserPointTrend(User $user, Team $team, int $days = 7): array
    {
        $days = max($days, 1);
        $endDate = Carbon::now()->endOfDay();
        $startDate = Carbon::now()->subDays($days - 1)->startOfDay();

        $dailyTotals = UserPoint::query()
            ->where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as day, SUM(points) as total_points')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total_points', 'day');

        $trend = [];

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dayKey = $date->toDateString();
            $trend[] = [
                'date' => $dayKey,
                'label' => $date->format('d.m.'),
                'points' => (int) ($dailyTotals[$dayKey] ?? 0),
            ];
        }

        return $trend;
    }

    /**
     * Create a leaderboard for the given team and highlight the current user.
     *
     * @return array<int, array{rank:int|null,name:string,points:int,is_current_user:bool,is_additional?:bool}>
     */
    public function getTeamLeaderboard(Team $team, User $user, int $limit = 5): array
    {
        return $this->buildLeaderboard($this->getLeaderboardTotals($team), $user, $team, $limit);
    }

    /**
     * Determine the user rank for the given team.
     */
    public function getUserRank(User $user, Team $team): ?int
    {
        $rankData = $this->resolveUserRankData($user, $this->getLeaderboardTotals($team));

        return $rankData['rank'];
    }

    /**
     * Ensure the authenticated user has at least the required points.
     *
     * @throws AuthorizationException
     */
    public function assertMinPoints(int $required): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            throw new AuthorizationException('Nicht authentifiziert.');
        }

        if ($this->getUserPoints($user) < $required) {
            throw new AuthorizationException("Mindestens {$required} Baxx erforderlich.");
        }
    }

    /**
     * @param  Collection<int, array{user_id:int,total:int}>  $totals
     */
    private function calculateTeamAverage(Team $team, Collection $totals): float
    {
        $memberIds = $team->activeUsers()->pluck('users.id');

        if ($memberIds->isEmpty()) {
            return 0.0;
        }

        $totalPoints = $memberIds
            ->map(function (int $memberId) use ($totals) {
                $entry = $totals->firstWhere('user_id', $memberId);

                return $entry['total'] ?? 0;
            })
            ->sum();

        return round($totalPoints / $memberIds->count(), 1);
    }

    private function calculateProgress(int|float $value, int|float $target): int
    {
        if ($target <= 0) {
            return 0;
        }

        $percentage = ($value / $target) * 100;

        return (int) max(0, min(100, round($percentage)));
    }

    private function calculateWeeklyTarget(User $user, Team $team, int $days = 7): int
    {
        $days = max($days, 1);

        $memberIds = $team->activeUsers()->pluck('users.id');
        $otherMemberIds = $memberIds->reject(fn (int $memberId) => $memberId === $user->id)->values();

        if ($otherMemberIds->isEmpty()) {
            return 0;
        }

        $endDate = Carbon::now()->endOfDay();
        $startDate = Carbon::now()->subDays($days - 1)->startOfDay();

        $totals = UserPoint::query()
            ->where('team_id', $team->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('user_id, SUM(points) as total_points')
            ->groupBy('user_id')
            ->pluck('total_points', 'user_id');

        $sum = $otherMemberIds
            ->map(fn (int $memberId) => (int) ($totals[$memberId] ?? 0))
            ->sum();

        $average = $sum / $otherMemberIds->count();

        return (int) round($average);
    }

    /**
     * @return Collection<int, array{user_id:int,total:int}>
     */
    private function getLeaderboardTotals(Team $team): Collection
    {
        return UserPoint::query()
            ->where('team_id', $team->id)
            ->selectRaw('user_id, SUM(points) as total_points')
            ->groupBy('user_id')
            ->orderByDesc('total_points')
            ->get()
            ->map(fn (UserPoint $row) => [
                'user_id' => (int) $row->user_id,
                'total' => (int) $row->total_points,
            ]);
    }

    /**
     * @param  Collection<int, array{user_id:int,total:int}>  $totals
     * @return array{rank:int|null,points_to_next_rank:int|null,next_rank_points:int|null}
     */
    private function resolveUserRankData(User $user, Collection $totals): array
    {
        $rank = null;
        $pointsToNext = null;
        $nextRankPoints = null;

        foreach ($totals as $index => $entry) {
            if ($entry['user_id'] === $user->id) {
                $rank = $index + 1;
                if ($index > 0) {
                    $nextEntry = $totals->get($index - 1);
                    $nextRankPoints = $nextEntry['total'];
                    $pointsToNext = max(0, $nextEntry['total'] - $entry['total']);
                }
                break;
            }
        }

        return [
            'rank' => $rank,
            'points_to_next_rank' => $pointsToNext,
            'next_rank_points' => $nextRankPoints,
        ];
    }

    /**
     * @param  Collection<int, array{user_id:int,total:int}>  $totals
     * @return array<int, array{rank:int|null,name:string,points:int,is_current_user:bool,is_additional?:bool}>
     */
    private function buildLeaderboard(Collection $totals, User $user, Team $team, int $limit = 5): array
    {
        $limit = max(1, $limit);
        $topEntries = $totals->take($limit);
        $userIds = $topEntries->pluck('user_id')->push($user->id)->unique()->values();

        $userNames = $team->users()
            ->whereIn('users.id', $userIds)
            ->pluck('users.name', 'users.id');

        $leaderboard = [];

        foreach ($topEntries as $index => $entry) {
            $leaderboard[] = [
                'rank' => $index + 1,
                'name' => $userNames[$entry['user_id']] ?? 'Unbekannt',
                'points' => $entry['total'],
                'is_current_user' => $entry['user_id'] === $user->id,
            ];
        }

        $alreadyIncluded = collect($leaderboard)->contains(fn (array $row) => $row['is_current_user']);

        if (! $alreadyIncluded) {
            $userEntry = $totals->firstWhere('user_id', $user->id);
            $position = null;

            if ($userEntry) {
                $foundIndex = $totals->search($userEntry);
                $position = $foundIndex !== false ? $foundIndex + 1 : null;
            }

            $leaderboard[] = [
                'rank' => $position,
                'name' => $userNames[$user->id] ?? $user->name,
                'points' => $userEntry['total'] ?? 0,
                'is_current_user' => true,
                'is_additional' => true,
            ];
        }

        return $leaderboard;
    }
}

