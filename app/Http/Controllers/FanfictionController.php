<?php

namespace App\Http\Controllers;

use App\Enums\FanfictionStatus;
use App\Enums\Role;
use App\Http\Controllers\Concerns\MembersTeamAware;
use App\Models\Fanfiction;
use App\Models\User;
use App\Services\FanfictionAccessService;
use App\Services\RewardService;
use App\Services\UserRoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FanfictionController extends Controller
{
    use MembersTeamAware;

    public function __construct(
        private readonly UserRoleService $userRoleService,
        private readonly RewardService $rewardService,
        private readonly FanfictionAccessService $fanfictionAccessService,
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

        /** @var User $user */
        $user = Auth::user();
        $autoRefundedPurchases = $this->fanfictionAccessService->refundOwnPurchases($user);
        $walletState = $this->rewardService->getWalletState($user);
        $unlockedRewardIds = $this->rewardService->getUnlockedRewardIds($user);
        $unlockedFanfictionIds = $this->fanfictionAccessService->getUnlockedFanfictionIds(
            $user,
            $fanfictions->getCollection(),
            $unlockedRewardIds,
        );
        $ownFanfictionIds = $fanfictions->getCollection()
            ->filter(fn (Fanfiction $fanfiction) => $this->fanfictionAccessService->isOwnContribution($user, $fanfiction))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return view('fanfiction.index', [
            'fanfictions' => $fanfictions,
            'role' => $role,
            'availableBaxx' => $walletState['availableBaxx'],
            'walletWarning' => $walletState['warning'],
            'unlockedFanfictionIds' => $unlockedFanfictionIds,
            'ownFanfictionIds' => $ownFanfictionIds,
            'autoRefundedPurchases' => $autoRefundedPurchases,
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

        /** @var User $user */
        $user = Auth::user();
        $walletState = $this->rewardService->getWalletState($user);
        $isOwnFanfiction = $this->fanfictionAccessService->isOwnContribution($user, $fanfiction);
        $autoRefundedPurchases = $isOwnFanfiction
            ? $this->fanfictionAccessService->refundOwnPurchases($user)
            : 0;
        $hasUnlocked = $this->fanfictionAccessService->hasUnlocked($user, $fanfiction);

        return view('fanfiction.show', [
            'fanfiction' => $fanfiction,
            'role' => $role,
            'hasUnlocked' => $hasUnlocked,
            'isOwnFanfiction' => $isOwnFanfiction,
            'availableBaxx' => $walletState['availableBaxx'],
            'walletWarning' => $walletState['warning'],
            'autoRefundedPurchases' => $autoRefundedPurchases,
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

        /** @var User $user */
        $user = Auth::user();
        if ($this->fanfictionAccessService->isOwnContribution($user, $fanfiction)) {
            $autoRefundedPurchases = $this->fanfictionAccessService->refundOwnPurchases($user);
            $message = $autoRefundedPurchases > 0
                ? 'Deine eigene Fanfiction ist bereits freigeschaltet. Frühere Eigenkäufe wurden automatisch erstattet.'
                : 'Deine eigene Fanfiction ist bereits freigeschaltet.';

            return redirect()->route('fanfiction.show', $fanfiction)
                ->with('info', $message);
        }

        try {
            $this->rewardService->purchaseReward($user, $fanfiction->reward);

            return redirect()->route('fanfiction.show', $fanfiction)
                ->with('success', 'Fanfiction erfolgreich freigeschaltet! Viel Spaß beim Lesen.');
        } catch (ValidationException $e) {
            return redirect()->route('fanfiction.show', $fanfiction)
                ->withErrors($e->errors());
        }
    }
}
