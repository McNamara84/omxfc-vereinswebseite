<?php

namespace App\Services;

use App\Models\BaxxEarningRule;
use App\Models\ReviewBaxxSpecialOffer;
use App\Models\User;
use App\Services\Baxx\BaxxBookingWriter;
use App\Services\Baxx\BaxxRulePresenter;
use App\Services\Baxx\BaxxRuleResolver;
use Carbon\CarbonInterface;

class ReviewBaxxService
{
    public function __construct(
        private readonly BaxxRuleResolver $rules,
        private readonly BaxxRulePresenter $presenter,
        private readonly BaxxBookingWriter $writer,
    ) {}

    public function getEffectiveRule(): array
    {
        return $this->presenter->present($this->rules->resolve('rezension'), 'Rezension', 'Rezensionen', 'review');
    }

    public function getBaseRuleData(): array
    {
        return $this->presenter->present($this->rules->resolve('rezension', false), 'Rezension', 'Rezensionen', 'review');
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
            'prominent_special_offer' => $rule['is_special_offer'] && $rule['points_per_review'] >= 1 ? $rule : null,
        ];
    }

    public function awardPointsForReview(User $user, int $reviewCount, ?int $teamId = null, ?CarbonInterface $awardedAt = null): int
    {
        $points = $this->rules->resolve('rezension')->pointsAt($reviewCount);

        if ($points === 0) {
            return 0;
        }

        $resolvedTeamId = $teamId ?? $user->currentTeam?->id;

        if (! $resolvedTeamId) {
            return 0;
        }

        $this->writer->record($user->id, $resolvedTeamId, $points, occurredAt: $awardedAt);

        return $points;
    }

    public function getBaseRule(): BaxxEarningRule
    {
        return $this->rules->base('rezension');
    }

    public function getActiveSpecialOffer(): ?ReviewBaxxSpecialOffer
    {
        return $this->rules->activeOffer('rezension');
    }
}
