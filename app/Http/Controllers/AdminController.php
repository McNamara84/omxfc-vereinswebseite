<?php

namespace App\Http\Controllers;

use App\Models\PageVisit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class AdminController extends Controller
{
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

        return view('admin.index', compact('visitData', 'userVisitData', 'activityData', 'homepageVisits'));
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
