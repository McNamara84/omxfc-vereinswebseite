<?php

namespace App\Http\Controllers;

use App\Models\ThreeDModel;
use App\Models\User;
use App\Services\TeamPointService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * @deprecated Ersetzt durch \App\Livewire\BelohnungenIndex (aktives Kaufsystem).
 * Diese Klasse wird nicht mehr über Routen angesprochen und kann entfernt werden.
 *
 * @see \App\Livewire\BelohnungenIndex
 * @see \App\Livewire\BelohnungenAdmin
 */
class RewardController extends Controller
{
    public function __construct(private TeamPointService $teamPointService) {}

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

        // 3D-Modelle dynamisch als Belohnungen hinzufügen
        $threeDModels = ThreeDModel::orderBy('required_baxx')->get();
        foreach ($threeDModels as $model) {
            $rewards[] = [
                'title' => '3D-Modell - '.$model->name,
                'description' => $model->description,
                'points' => $model->required_baxx,
                'url' => route('3d-modelle.show', $model),
                'type' => '3d-modell',
            ];
        }

        // Nach Punkten sortieren (statische + dynamische Rewards gemischt)
        usort($rewards, fn ($a, $b) => $a['points'] <=> $b['points']);

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
