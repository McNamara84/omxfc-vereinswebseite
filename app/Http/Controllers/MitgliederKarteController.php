<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\Reward;
use App\Services\MemberMapCacheService;
use App\Services\RewardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class MitgliederKarteController extends Controller
{
    public function __construct(
        protected MemberMapCacheService $memberMapCacheService,
        protected RewardService $rewardService,
    ) {}

    public function index()
    {
        $user = Auth::user();

        $reward = $this->mitgliederkarteReward();
        $hasAccess = $this->rewardService->hasUnlockedRewardId($user, $reward->id);
        $walletState = $hasAccess
            ? ['warning' => null, 'availableBaxx' => null]
            : $this->rewardService->getWalletState($user);

        $mapData = $this->defaultMapData();

        if ($hasAccess) {
            $membersTeam = Team::membersTeam();

            if ($membersTeam) {
                $mapData = $this->memberMapCacheService->getMemberMapData($membersTeam);
            }
        }

        $memberData = $mapData['memberData'];
        $centerLat = $mapData['centerLat'];
        $centerLon = $mapData['centerLon'];

        $availableBaxx = $walletState['availableBaxx'];
        $missingBaxx = ! $hasAccess && is_int($availableBaxx)
            ? max(0, $reward->cost_baxx - $availableBaxx)
            : null;

        return view('mitglieder.karte', [
            'memberData' => json_encode($memberData),
            'stammtischData' => json_encode($hasAccess ? $this->stammtischData() : []),
            'centerLat' => 51.1657, // Mitte von Deutschland
            'centerLon' => 10.4515,
            'membersCenterLat' => $centerLat,
            'membersCenterLon' => $centerLon,
            'isUnlocked' => $hasAccess,
            'reward' => $reward,
            'walletWarning' => $walletState['warning'],
            'availableBaxx' => $availableBaxx,
            'missingBaxx' => $missingBaxx,
            'canPurchase' => ! $hasAccess
                && $reward->is_active
                && is_int($availableBaxx)
                && $availableBaxx >= $reward->cost_baxx,
        ]);
    }

    public function purchase(): RedirectResponse
    {
        $user = Auth::user();
        $reward = $this->mitgliederkarteReward();

        try {
            $this->rewardService->purchaseReward($user, $reward);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('mitglieder.karte')
                ->withErrors($exception->errors());
        } catch (\Throwable $exception) {
            if (! $exception instanceof \LogicException) {
                report($exception);
            }

            return redirect()
                ->route('mitglieder.karte')
                ->withErrors([
                    'reward' => 'Die Mitgliederkarte konnte aktuell nicht freigeschaltet werden. Bitte versuche es später erneut.',
                ]);
        }

        return redirect()
            ->route('mitglieder.karte')
            ->with('success', 'Die Mitgliederkarte wurde freigeschaltet.');
    }

    public function locked(): RedirectResponse
    {
        return redirect()->route('mitglieder.karte');
    }

    private function mitgliederkarteReward(): Reward
    {
        return $this->rewardService->resolveMitgliederkarteReward();
    }

    /**
     * @return array{memberData: array<int, array<string, mixed>>, centerLat: float, centerLon: float}
     */
    private function defaultMapData(): array
    {
        return [
            'memberData' => [],
            'centerLat' => 51.1657,
            'centerLon' => 10.4515,
        ];
    }

    /**
     * @return array<int, array{name: string, lat: float, lon: float, address: string, info: string}>
     */
    private function stammtischData(): array
    {
        return [
            [
                'name' => 'Regionalstammtisch München',
                'lat' => 48.12896638040895,
                'lon' => 11.609687426607499,
                'address' => 'München, Bayern',
                'info' => 'Jeden ersten Donnerstag im Monat',
            ],
            [
                'name' => 'Regionalstammtisch Berlin',
                'lat' => 52.4612530430613,
                'lon' => 13.318158251047139,
                'address' => 'Berlin',
                'info' => 'Jeden siebten Tag in geraden Monaten',
            ],
            [
                'name' => 'Regionalstammtisch Brandenburg',
                'lat' => 52.40084391069621,
                'lon' => 13.0538574534862,
                'address' => 'Brandenburg',
                'info' => 'Jeden siebten Tag in ungeraden Monaten',
            ],
        ];
    }
}
