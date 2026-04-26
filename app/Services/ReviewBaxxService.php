<?php

namespace App\Services;

use App\Models\BaxxEarningRule;
use App\Models\ReviewBaxxSpecialOffer;
use App\Models\User;
use App\Models\UserPoint;
use Carbon\CarbonInterface;

class ReviewBaxxService
{
    private ?BaxxEarningRule $baseRule = null;

    private bool $activeSpecialOfferLoaded = false;

    private ?ReviewBaxxSpecialOffer $activeSpecialOffer = null;

    /**
     * @return array{
     *     points:int,
     *     every_count:int,
     *     is_active:bool,
     *     is_special_offer:bool,
     *     points_per_review:float,
     *     points_per_review_label:string,
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
                endsAt: $specialOffer->ends_at
            );
        }

        $baseRule = $this->getBaseRule();

        return $this->makeRuleData(
            points: $baseRule->points,
            everyCount: $baseRule->every_count,
            isActive: $baseRule->is_active,
            isSpecialOffer: false,
            endsAt: null
        );
    }

    /**
     * @return array{
     *     points:int,
     *     every_count:int,
     *     is_active:bool,
     *     is_special_offer:bool,
     *     points_per_review:float,
     *     points_per_review_label:string,
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
            endsAt: null
        );
    }

    /**
     * @return array{
     *     points:int,
     *     every_count:int,
     *     is_active:bool,
     *     is_special_offer:bool,
     *     points_per_review:float,
     *     points_per_review_label:string,
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
     *         points_per_review:float,
     *         points_per_review_label:string,
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
     *         points_per_review:float,
     *         points_per_review_label:string,
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

    public function awardPointsForReview(User $user, int $reviewCount, ?int $teamId = null, ?CarbonInterface $awardedAt = null): int
    {
        $effectiveRule = $this->getEffectiveRule();

        if (! $effectiveRule['is_active'] || $effectiveRule['points'] <= 0) {
            return 0;
        }

        if ($reviewCount <= 0 || $reviewCount % $effectiveRule['every_count'] !== 0) {
            return 0;
        }

        $resolvedTeamId = $teamId ?? $user->currentTeam?->id;

        if (! $resolvedTeamId) {
            return 0;
        }

        $userPoint = new UserPoint([
            'user_id' => $user->id,
            'team_id' => $resolvedTeamId,
            'points' => $effectiveRule['points'],
        ]);

        if ($awardedAt) {
            $userPoint->created_at = $awardedAt;
            $userPoint->updated_at = $awardedAt;
        }

        $userPoint->save();

        return $effectiveRule['points'];
    }

    public function getBaseRule(): BaxxEarningRule
    {
        if ($this->baseRule) {
            return $this->baseRule;
        }

        return $this->baseRule = BaxxEarningRule::query()->firstOrCreate(
            ['action_key' => 'rezension'],
            [
                'label' => 'Rezension-Meilenstein',
                'description' => '1 Baxx für jede 10. Rezension eines Mitglieds.',
                'points' => 1,
                'every_count' => 10,
                'is_active' => true,
            ]
        );
    }

    public function getActiveSpecialOffer(): ?ReviewBaxxSpecialOffer
    {
        if ($this->activeSpecialOfferLoaded) {
            return $this->activeSpecialOffer;
        }

        $this->activeSpecialOfferLoaded = true;

        return $this->activeSpecialOffer = ReviewBaxxSpecialOffer::query()
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
     *     points_per_review:float,
     *     points_per_review_label:string,
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
     *     points_per_review:float,
     *     points_per_review_label:string,
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

        return $effectiveRule['points_per_review'] >= 1 ? $effectiveRule : null;
    }

    private function makeRuleData(
        int $points,
        int $everyCount,
        bool $isActive,
        bool $isSpecialOffer,
        ?CarbonInterface $endsAt,
    ): array {
        $everyCount = max(1, $everyCount);
        $pointsPerReview = $points / $everyCount;
        $pointsPerReviewLabel = $this->formatNumber($pointsPerReview);

        return [
            'points' => $points,
            'every_count' => $everyCount,
            'is_active' => $isActive,
            'is_special_offer' => $isSpecialOffer,
            'points_per_review' => $pointsPerReview,
            'points_per_review_label' => $pointsPerReviewLabel,
            'rule_label' => $this->formatRuleLabel($points, $everyCount),
            'ends_at' => $endsAt,
            'ends_at_formatted' => $endsAt?->copy()->timezone(config('app.timezone'))->format('d.m.Y, H:i'),
            'banner_text' => $isSpecialOffer
                ? 'Sonderaktion: Jetzt für einen begrenzten Zeitraum '.$pointsPerReviewLabel.' Baxx pro Rezension!'
                : null,
            'banner_end_text' => $endsAt
                ? 'Die Aktion endet am '.$endsAt->copy()->timezone(config('app.timezone'))->format('d.m.Y').' um '.$endsAt->copy()->timezone(config('app.timezone'))->format('H:i').' Uhr.'
                : null,
        ];
    }

    private function formatRuleLabel(int $points, int $everyCount): string
    {
        if ($everyCount === 1) {
            return $points.' Baxx pro Rezension';
        }

        return $points.' Baxx pro '.$everyCount.' Rezensionen';
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