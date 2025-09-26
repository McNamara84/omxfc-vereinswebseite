<?php

namespace App\Http\Controllers;

use App\Models\PageVisit;
use App\Services\BrowserStatsService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function __construct(
        private readonly BrowserStatsService $browserStatsService
    ) {
    }

    public function index()
    {
        $rawVisitData = PageVisit::select('path', DB::raw('COUNT(*) as total'))
            ->groupBy('path')
            ->orderByDesc('total')
            ->get();

        $normalizedVisitData = $rawVisitData
            ->map(fn ($row) => [
                'path' => $this->normalizePath($row->path),
                'total' => (int) $row->total,
            ])
            ->groupBy('path')
            ->map(function (Collection $rows) {
                return [
                    'path' => $rows->first()['path'],
                    'total' => $rows->sum('total'),
                ];
            })
            ->values();

        $homepageEntry = $normalizedVisitData->firstWhere('path', '/');
        $homepageVisits = (int) ($homepageEntry['total'] ?? 0);

        $visitData = $normalizedVisitData
            ->reject(fn ($row) => $row['path'] === '/')
            ->sortByDesc('total')
            ->values();

        $rawUserVisitData = PageVisit::select('path', 'user_id', DB::raw('COUNT(*) as total'))
            ->with('user:id,name')
            ->groupBy('path', 'user_id')
            ->get();

        $userVisitData = $rawUserVisitData
            ->map(function ($row) {
                $normalizedPath = $this->normalizePath($row->path);

                return [
                    'path' => $normalizedPath,
                    'user_id' => $row->user_id,
                    'user' => [
                        'id' => $row->user->id,
                        'name' => $row->user->name,
                    ],
                    'total' => (int) $row->total,
                ];
            })
            ->groupBy(fn ($row) => $row['path'] . '|' . $row['user_id'])
            ->map(function (Collection $rows) {
                $first = $rows->first();

                return [
                    'path' => $first['path'],
                    'user_id' => $first['user_id'],
                    'user' => $first['user'],
                    'total' => $rows->sum('total'),
                ];
            })
            ->values()
            ->reject(fn ($row) => $row['path'] === '/')
            ->values();

        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            $activityRaw = PageVisit::selectRaw('strftime("%w", created_at) as weekday, strftime("%H", created_at) as hour, COUNT(DISTINCT user_id) as total')
                ->groupBy('weekday', 'hour')
                ->orderBy('weekday')
                ->orderBy('hour')
                ->get();
        } else {
            $activityRaw = PageVisit::selectRaw('WEEKDAY(created_at) as weekday, HOUR(created_at) as hour, COUNT(DISTINCT user_id) as total')
                ->groupBy('weekday', 'hour')
                ->orderBy('weekday')
                ->orderBy('hour')
                ->get();
        }

        $activityData = [];
        for ($day = 0; $day < 7; $day++) {
            for ($hour = 0; $hour < 24; $hour++) {
                $activityData[$day][$hour] = 0;
            }
        }

        foreach ($activityRaw as $row) {
            $weekday = $driver === 'sqlite' ? ((int) $row->weekday + 6) % 7 : (int) $row->weekday;
            $hour = (int) $row->hour;
            $activityData[$weekday][$hour] = (int) $row->total;
        }

        for ($hour = 0; $hour < 24; $hour++) {
            $sum = 0;
            for ($day = 0; $day < 7; $day++) {
                $sum += $activityData[$day][$hour];
            }
            $activityData['all'][$hour] = $sum / 7;
        }

        $activityTimeline = collect(range(0, 6))
            ->flatMap(function (int $day) use ($activityData) {
                return collect(range(0, 23))->map(function (int $hour) use ($activityData, $day) {
                    return [
                        'weekday' => $day,
                        'hour' => $hour,
                        'total' => $activityData[$day][$hour] ?? 0,
                    ];
                });
            })
            ->values();

        $dailyActiveUsers = $this->dailyActiveUsers();

        $browserUsage = $this->browserStatsService->browserUsage();

        return view('admin.index', [
            'visitData' => $visitData,
            'userVisitData' => $userVisitData,
            'activityData' => $activityData,
            'activityTimeline' => $activityTimeline,
            'homepageVisits' => $homepageVisits,
            'dailyActiveUsers' => $dailyActiveUsers,
            'browserUsageByBrowser' => $browserUsage['browserCounts'],
            'browserUsageByFamily' => $browserUsage['familyCounts'],
            'deviceUsage' => $browserUsage['deviceTypeCounts'],
        ]);
    }

    private function dailyActiveUsers(): array
    {
        $driver = DB::getDriverName();

        $baseQuery = PageVisit::query();
        if ($driver === 'sqlite') {
            $baseQuery->selectRaw('date(created_at) as visit_date, COUNT(DISTINCT user_id) as total');
        } else {
            $baseQuery->selectRaw('DATE(created_at) as visit_date, COUNT(DISTINCT user_id) as total');
        }

        $raw = $baseQuery
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->groupBy('visit_date')
            ->orderByDesc('visit_date')
            ->get();

        $dates = collect(range(0, 29))->map(fn (int $offset) => Carbon::today()->subDays($offset));

        $rawMap = $raw
            ->mapWithKeys(function ($row) {
                $date = Carbon::parse($row->visit_date)->toDateString();

                return [
                    $date => (int) $row->total,
                ];
            });

        $series = $dates
            ->map(function (Carbon $date) use ($rawMap) {
                $dateString = $date->toDateString();

                return [
                    'date' => $dateString,
                    'total' => $rawMap->get($dateString, 0),
                ];
            })
            ->reverse()
            ->values();

        $today = Carbon::today()->toDateString();
        $yesterday = Carbon::yesterday()->toDateString();

        $todayTotal = $rawMap->get($today, 0);
        $yesterdayTotal = $rawMap->get($yesterday, 0);

        $sevenDayAverage = $dates
            ->take(7)
            ->map(fn (Carbon $date) => $rawMap->get($date->toDateString(), 0))
            ->avg() ?? 0;

        return [
            'today' => $todayTotal,
            'yesterday' => $yesterdayTotal,
            'seven_day_average' => round($sevenDayAverage, 1),
            'trend' => $todayTotal - $yesterdayTotal,
            'series' => $series->all(),
        ];
    }

    private function normalizePath(?string $path): string
    {
        if ($path === null || $path === '') {
            return '/';
        }

        $cleanPath = explode('?', $path, 2)[0] ?? '';
        $cleanPath = '/' . ltrim($cleanPath, '/');

        if ($cleanPath === '/' || $cleanPath === '') {
            return '/';
        }

        $segments = explode('/', trim($cleanPath, '/'));
        $firstSegment = $segments[0] ?? '';

        return $firstSegment === '' ? '/' : '/' . $firstSegment;
    }
}
