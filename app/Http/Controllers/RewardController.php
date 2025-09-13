<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TeamPointService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RewardController extends Controller
{
    public function __construct(private TeamPointService $teamPointService)
    {
    }

    /**
     * Display a listing of rewards.
     */
    public function index(): View
    {
        /** @var User $user */
        $user = Auth::user();
        $currentTeam = $user->currentTeam;
        $userPoints = $this->teamPointService->getUserPoints($user);

        $rewards = config('rewards', []);

        if ($currentTeam) {
            $members = $currentTeam->activeUsers()->get();
            $totalMembers = $members->count();

            foreach ($rewards as &$reward) {
                $required = $reward['points'];
                $unlockedCount = $members->filter(function (User $member) use ($required) {
                    return $this->teamPointService->getUserPoints($member) >= $required;
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
