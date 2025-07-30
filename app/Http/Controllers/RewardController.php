<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RewardController extends Controller
{
    /**
     * Display a listing of rewards.
     */
    public function index(): View
    {
        /** @var User $user */
        $user = Auth::user();
        $currentTeam = $user->currentTeam;
        $userPoints = $currentTeam ? $user->totalPointsForTeam($currentTeam) : 0;

        $rewards = config('rewards', []);

        if ($currentTeam) {
            $members = $currentTeam->users()
                ->wherePivotNotIn('role', ['Anwärter'])
                ->get();
            $totalMembers = $members->count();

            foreach ($rewards as &$reward) {
                $required = $reward['points'];
                $unlockedCount = $members->filter(function (User $member) use ($currentTeam, $required) {
                    return $member->totalPointsForTeam($currentTeam) >= $required;
                })->count();
                $reward['percentage'] = $totalMembers > 0 ? round(($unlockedCount / $totalMembers) * 100) : 0;
            }
            unset($reward);
        }

        return view('rewards.index', [
            'rewards' => $rewards,
            'userPoints' => $userPoints,
        ]);
    }
}
