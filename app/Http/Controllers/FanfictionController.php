<?php

namespace App\Http\Controllers;

use App\Enums\FanfictionStatus;
use App\Enums\Role;
use App\Http\Controllers\Concerns\MembersTeamAware;
use App\Models\Fanfiction;
use App\Services\RewardService;
use App\Services\UserRoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class FanfictionController extends Controller
{
    use MembersTeamAware;

    public function __construct(
        private readonly UserRoleService $userRoleService,
        private readonly RewardService $rewardService,
    ) {}

    protected function getUserRoleService(): UserRoleService
    {
        return $this->userRoleService;
    }

    /**
     * Öffentliche Ansicht für Gäste (Teaser).
     */
    public function publicIndex(): View
    {
        $fanfictions = Fanfiction::with('author')
            ->published()
            ->forTeam($this->memberTeam()->id)
            ->orderByDesc('published_at')
            ->paginate(12);

        return view('fanfiction.public-index', [
            'fanfictions' => $fanfictions,
        ]);
    }

    /**
     * Übersicht für eingeloggte Mitglieder.
     */
    public function index(Request $request): View
    {
        $role = $this->authorizeMemberArea();

        $query = Fanfiction::with(['author', 'comments.user', 'reward'])
            ->published()
            ->forTeam($this->memberTeam()->id);

        // Optional: Filter by author
        if ($request->filled('author')) {
            $query->where('user_id', $request->input('author'));
        }

        $fanfictions = $query->orderByDesc('published_at')->paginate(15);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $availableBaxx = $this->rewardService->getAvailableBaxx($user);

        // Einmalig alle freigeschalteten Reward-IDs laden (statt N+1 Queries)
        $unlockedRewardIds = $this->rewardService->getUnlockedRewardIds($user);

        // Fanfictions ohne Reward gelten als kostenlos/freigeschaltet
        $unlockedFanfictionIds = $fanfictions->filter(function ($fanfiction) use ($unlockedRewardIds) {
            return ! $fanfiction->reward
                || in_array($fanfiction->reward_id, $unlockedRewardIds, true);
        })->pluck('id')->toArray();

        return view('fanfiction.index', [
            'fanfictions' => $fanfictions,
            'role' => $role,
            'availableBaxx' => $availableBaxx,
            'unlockedFanfictionIds' => $unlockedFanfictionIds,
        ]);
    }

    /**
     * Einzelansicht einer Fanfiction mit Kommentaren.
     */
    public function show(Fanfiction $fanfiction): View
    {
        $role = $this->authorizeMemberArea();

        // Team-Scoping: Fanfiction muss zum Mitglieder-Team gehören
        if ($fanfiction->team_id !== $this->memberTeam()->id) {
            abort(404);
        }

        // Ensure fanfiction is published (unless user is Vorstand/Admin)
        if ($fanfiction->status !== FanfictionStatus::Published && ! in_array($role, [Role::Vorstand, Role::Admin], true)) {
            abort(404);
        }

        $fanfiction->load(['author', 'comments.user', 'comments.children.user', 'reward']);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $hasUnlocked = ! $fanfiction->reward
            || $this->rewardService->hasUnlockedReward($user, $fanfiction->reward->slug);
        $availableBaxx = $this->rewardService->getAvailableBaxx($user);

        return view('fanfiction.show', [
            'fanfiction' => $fanfiction,
            'role' => $role,
            'hasUnlocked' => $hasUnlocked,
            'availableBaxx' => $availableBaxx,
        ]);
    }

    /**
     * Kauft eine Fanfiction mit Baxx.
     */
    public function purchase(Fanfiction $fanfiction): RedirectResponse
    {
        $this->authorizeMemberArea();

        // Team-Scoping: Fanfiction muss zum Mitglieder-Team gehören
        if ($fanfiction->team_id !== $this->memberTeam()->id) {
            abort(404);
        }

        // Nur veröffentlichte Fanfictions können gekauft werden
        if ($fanfiction->status !== FanfictionStatus::Published) {
            abort(404);
        }

        $fanfiction->loadMissing('reward');

        if (! $fanfiction->reward) {
            return redirect()->route('fanfiction.show', $fanfiction)
                ->with('info', 'Diese Fanfiction ist kostenlos verfügbar.');
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        try {
            $this->rewardService->purchaseReward($user, $fanfiction->reward);

            return redirect()->route('fanfiction.show', $fanfiction)
                ->with('success', 'Fanfiction erfolgreich freigeschaltet! Viel Spaß beim Lesen.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('fanfiction.show', $fanfiction)
                ->withErrors($e->errors());
        }
    }
}
