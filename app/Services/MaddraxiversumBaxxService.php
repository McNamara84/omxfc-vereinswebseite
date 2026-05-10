<?php

namespace App\Services;

use App\Models\BaxxEarningRule;
use App\Models\MaddraxiversumBaxxSpecialOffer;
use App\Models\Mission;
use App\Models\User;
use App\Models\UserPoint;
use Carbon\CarbonInterface;

class MaddraxiversumBaxxService
{
    private ?BaxxEarningRule $baseRule = null;

    private bool $activeSpecialOfferLoaded = false;

    private ?MaddraxiversumBaxxSpecialOffer $activeSpecialOffer = null;

    /**
     * @return array{
     *     points:int,
     *     every_count:int,
     *     is_active:bool,
     *     is_special_offer:bool,
     *     points_per_mission:float,
     *     points_per_mission_label:string,
     *     rule_label:string,
     *     ends_at:?CarbonInterface,
     *     ends_at_formatted:?string,
     *     banner_text:?string,
     *     banner_end_text:?string
     * }
     */
    public function getEffectiveRule(): array
    {
        $specialOffer = $this->getActiveSpecialOffer();

        if ($specialOffer) {
            return $this->makeRuleData(
                points: $specialOffer->points,
                everyCount: $specialOffer->every_count,
                isActive: true,
                isSpecialOffer: true,
                endsAt: $specialOffer->ends_at,
            );
        }

        $baseRule = $this->getBaseRule();

        return $this->makeRuleData(
            points: $baseRule->points,
            everyCount: $baseRule->every_count,
            isActive: $baseRule->is_active,
            isSpecialOffer: false,
            endsAt: null,
        );
    }

    /**
     * @return array{
     *     points:int,
     *     every_count:int,
     *     is_active:bool,
     *     is_special_offer:bool,
     *     points_per_mission:float,
     *     points_per_mission_label:string,
     *     rule_label:string,
     *     ends_at:?CarbonInterface,
     *     ends_at_formatted:?string,
     *     banner_text:?string,
     *     banner_end_text:?string
     * }
     */
    public function getBaseRuleData(): array
    {
        $baseRule = $this->getBaseRule();

        return $this->makeRuleData(
            points: $baseRule->points,
            everyCount: $baseRule->every_count,
            isActive: $baseRule->is_active,
            isSpecialOffer: false,
            endsAt: null,
        );
    }

    /**
     * @return array{
     *     points:int,
     *     every_count:int,
     *     is_active:bool,
     *     is_special_offer:bool,
     *     points_per_mission:float,
     *     points_per_mission_label:string,
     *     rule_label:string,
     *     ends_at:?CarbonInterface,
     *     ends_at_formatted:?string,
     *     banner_text:?string,
     *     banner_end_text:?string
     * }|null
     */
    public function getProminentSpecialOffer(): ?array
    {
        return $this->extractProminentSpecialOffer($this->getEffectiveRule());
    }

    /**
     * @return array{
     *     effective_rule: array{
     *         points:int,
     *         every_count:int,
     *         is_active:bool,
     *         is_special_offer:bool,
     *         points_per_mission:float,
     *         points_per_mission_label:string,
     *         rule_label:string,
     *         ends_at:?CarbonInterface,
     *         ends_at_formatted:?string,
     *         banner_text:?string,
     *         banner_end_text:?string
     *     },
     *     prominent_special_offer: array{
     *         points:int,
     *         every_count:int,
     *         is_active:bool,
     *         is_special_offer:bool,
     *         points_per_mission:float,
     *         points_per_mission_label:string,
     *         rule_label:string,
     *         ends_at:?CarbonInterface,
     *         ends_at_formatted:?string,
     *         banner_text:?string,
     *         banner_end_text:?string
     *     }|null
     * }
     */
    public function getMemberConfiguration(): array
    {
        $effectiveRule = $this->getEffectiveRule();

        return [
            'effective_rule' => $effectiveRule,
            'prominent_special_offer' => $this->extractProminentSpecialOffer($effectiveRule),
        ];
    }

    public function resolveMissionRewardPoints(?Mission $mission = null, ?int $completedMissionCount = null): int
    {
        $missionReward = $mission?->getAttribute('reward');

        if (is_numeric($missionReward) && (int) $missionReward > 0) {
            return (int) $missionReward;
        }

        $effectiveRule = $this->getEffectiveRule();

        if (! $effectiveRule['is_active'] || $effectiveRule['points'] <= 0) {
            return 0;
        }

        if ($completedMissionCount !== null && ($completedMissionCount <= 0 || $completedMissionCount % $effectiveRule['every_count'] !== 0)) {
            return 0;
        }

        return $effectiveRule['points'];
    }

    public function awardPointsForMission(User $user, ?Mission $mission = null, ?int $completedMissionCount = null, ?CarbonInterface $awardedAt = null): int
    {
        $points = $this->resolveMissionRewardPoints($mission, $completedMissionCount);
        $teamId = $user->currentTeam?->id;

        if ($points <= 0 || ! $teamId) {
            return 0;
        }

        $userPoint = new UserPoint([
            'user_id' => $user->id,
            'team_id' => $teamId,
            'points' => $points,
        ]);

        if ($awardedAt) {
            $userPoint->created_at = $awardedAt;
            $userPoint->updated_at = $awardedAt;
        }

        $userPoint->save();

        return $points;
    }

    public function getBaseRule(): BaxxEarningRule
    {
        if ($this->baseRule) {
            return $this->baseRule;
        }

        return $this->baseRule = BaxxEarningRule::query()->firstOrCreate(
            ['action_key' => 'maddraxiversum_mission'],
            [
                'label' => 'Maddraxiversum-Mission',
                'description' => 'Standard-Baxx für eine abgeschlossene Maddraxiversum-Mission (kann pro Mission überschrieben werden).',
                'points' => 5,
                'every_count' => 1,
                'is_active' => true,
            ]
        );
    }

    public function getActiveSpecialOffer(): ?MaddraxiversumBaxxSpecialOffer
    {
        if ($this->activeSpecialOfferLoaded) {
            return $this->activeSpecialOffer;
        }

        $this->activeSpecialOfferLoaded = true;

        return $this->activeSpecialOffer = MaddraxiversumBaxxSpecialOffer::query()
            ->currentlyActive()
            ->orderByDesc('ends_at')
            ->first();
    }

    /**
     * @param  array{
     *     points:int,
     *     every_count:int,
     *     is_active:bool,
     *     is_special_offer:bool,
     *     points_per_mission:float,
     *     points_per_mission_label:string,
     *     rule_label:string,
     *     ends_at:?CarbonInterface,
     *     ends_at_formatted:?string,
     *     banner_text:?string,
     *     banner_end_text:?string
     * }  $effectiveRule
     * @return array{
     *     points:int,
     *     every_count:int,
     *     is_active:bool,
     *     is_special_offer:bool,
     *     points_per_mission:float,
     *     points_per_mission_label:string,
     *     rule_label:string,
     *     ends_at:?CarbonInterface,
     *     ends_at_formatted:?string,
     *     banner_text:?string,
     *     banner_end_text:?string
     * }|null
     */
    private function extractProminentSpecialOffer(array $effectiveRule): ?array
    {
        if (! $effectiveRule['is_special_offer']) {
            return null;
        }

        return $effectiveRule['points_per_mission'] >= 1 ? $effectiveRule : null;
    }

    /**
     * @return array{
     *     points:int,
     *     every_count:int,
     *     is_active:bool,
     *     is_special_offer:bool,
     *     points_per_mission:float,
     *     points_per_mission_label:string,
     *     rule_label:string,
     *     ends_at:?CarbonInterface,
     *     ends_at_formatted:?string,
     *     banner_text:?string,
     *     banner_end_text:?string
     * }
     */
    private function makeRuleData(
        int $points,
        int $everyCount,
        bool $isActive,
        bool $isSpecialOffer,
        ?CarbonInterface $endsAt,
    ): array {
        $everyCount = max(1, $everyCount);
        $pointsPerMission = $points / $everyCount;
        $pointsPerMissionLabel = $this->formatNumber($pointsPerMission);

        return [
            'points' => $points,
            'every_count' => $everyCount,
            'is_active' => $isActive,
            'is_special_offer' => $isSpecialOffer,
            'points_per_mission' => $pointsPerMission,
            'points_per_mission_label' => $pointsPerMissionLabel,
            'rule_label' => $this->formatRuleLabel($points, $everyCount),
            'ends_at' => $endsAt,
            'ends_at_formatted' => $endsAt?->copy()->timezone(config('app.timezone'))->format('d.m.Y, H:i'),
            'banner_text' => $isSpecialOffer
                ? 'Sonderaktion: Jetzt für einen begrenzten Zeitraum '.$pointsPerMissionLabel.' Baxx pro Mission!'
                : null,
            'banner_end_text' => $endsAt
                ? 'Die Aktion endet am '.$endsAt->copy()->timezone(config('app.timezone'))->format('d.m.Y').' um '.$endsAt->copy()->timezone(config('app.timezone'))->format('H:i').' Uhr.'
                : null,
        ];
    }

    private function formatRuleLabel(int $points, int $everyCount): string
    {
        if ($everyCount === 1) {
            return $points.' Baxx pro Mission';
        }

        return $points.' Baxx pro '.$everyCount.' Missionen';
    }

    private function formatNumber(float $value): string
    {
        if ((float) (int) $value === $value) {
            return (string) (int) $value;
        }

        $formatted = number_format($value, 2, ',', '');

        return rtrim(rtrim($formatted, '0'), ',');
    }
}