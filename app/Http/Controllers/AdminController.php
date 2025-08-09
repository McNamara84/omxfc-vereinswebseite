<?php

namespace App\Http\Controllers;

use App\Models\PageVisit;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function index()
    {
        $visitData = PageVisit::select('path', DB::raw('COUNT(*) as total'))
            ->groupBy('path')
            ->orderByDesc('total')
            ->get();

        $userVisitData = PageVisit::select('path', 'user_id', DB::raw('COUNT(*) as total'))
            ->with('user:id,name')
            ->groupBy('path', 'user_id')
            ->get();

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

        return view('admin.index', compact('visitData', 'userVisitData', 'activityData'));
    }
}
