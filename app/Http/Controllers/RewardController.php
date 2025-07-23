<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use App\Models\User;

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

        return view('rewards.index', [
            'rewards' => $rewards,
            'userPoints' => $userPoints,
        ]);
    }
}
