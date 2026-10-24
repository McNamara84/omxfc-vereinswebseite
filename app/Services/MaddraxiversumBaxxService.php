<?php

namespace App\Services;

use App\Models\BaxxEarningRule;
use App\Models\MaddraxiversumBaxxSpecialOffer;
use App\Models\Mission;
use App\Models\User;
use App\Services\Baxx\BaxxBookingWriter;
use App\Services\Baxx\BaxxRulePresenter;
use App\Services\Baxx\BaxxRuleResolver;
use Carbon\CarbonInterface;

class MaddraxiversumBaxxService
{
    public function __construct(
        private readonly BaxxRuleResolver $rules,
        private readonly BaxxRulePresenter $presenter,
        private readonly BaxxBookingWriter $writer,
    ) {}

    public function getEffectiveRule(): array
    {
        return $this->presenter->present($this->rules->resolve('maddraxiversum_mission'), 'Mission', 'Missionen', 'mission');
    }

    public function getBaseRuleData(): array
    {
        return $this->presenter->present($this->rules->resolve('maddraxiversum_mission', false), 'Mission', 'Missionen', 'mission');
    }

    public function getProminentSpecialOffer(): ?array
    {
        return $this->getMemberConfiguration()['prominent_special_offer'];
    }

    public function getMemberConfiguration(): array
    {
        $rule = $this->getEffectiveRule();

        return [
            'effective_rule' => $rule,
            'prominent_special_offer' => $rule['is_special_offer'] && $rule['points_per_mission'] >= 1 ? $rule : null,
        ];
    }

    public function resolveMissionRewardPoints(?Mission $mission = null, ?int $completedMissionCount = null): int
    {
        $missionReward = $mission?->getAttribute('reward');

        if (is_numeric($missionReward) && (int) $missionReward > 0) {
            return (int) $missionReward;
        }

        $rule = $this->rules->resolve('maddraxiversum_mission');

        return $rule->pointsAt($completedMissionCount ?? $rule->everyCount);
    }

    public function awardPointsForMission(User $user, ?Mission $mission = null, ?int $completedMissionCount = null, ?CarbonInterface $awardedAt = null): int
    {
        $points = $this->resolveMissionRewardPoints($mission, $completedMissionCount);
        $teamId = $user->currentTeam?->id;

        if ($points <= 0 || ! $teamId) {
            return 0;
        }

        $this->writer->record($user->id, $teamId, $points, occurredAt: $awardedAt);

        return $points;
    }

    public function getBaseRule(): BaxxEarningRule
    {
        return $this->rules->base('maddraxiversum_mission');
    }

    public function getActiveSpecialOffer(): ?MaddraxiversumBaxxSpecialOffer
    {
        return $this->rules->activeOffer('maddraxiversum_mission');
    }
}
