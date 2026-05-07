<?php

namespace App\Services\Romantausch;

use App\Models\BaxxEarningProgress;
use App\Models\BaxxEarningRule;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\BookSwap;
use App\Models\RomantauschBaxxSpecialOffer;
use App\Models\Team;
use App\Models\UserPoint;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;

class RomantauschBaxxService
{
    /**
     * @return array{
     *     action_key: string,
     *     action_label: string,
     *     points:int,
     *     every_count:int,
     *     is_active:bool,
     *     is_special_offer:bool,
     *     rule_label:string,
     *     ends_at:?CarbonInterface,
     *     ends_at_formatted:?string,
     *     description:?string
     * }
     */
    public function getEffectiveRule(string $actionKey): array
    {
        $specialOffer = $this->getActiveSpecialOffer($actionKey);

        if ($specialOffer) {
            return $this->makeRuleData(
                actionKey: $actionKey,
                points: $specialOffer->points,
                everyCount: $specialOffer->every_count,
                isActive: true,
                isSpecialOffer: true,
                endsAt: $specialOffer->ends_at,
                description: 'Aktive Sonderaktion überschreibt die Basisregel für diese Romantausch-Aktion.',
            );
        }

        $baseRule = $this->getBaseRule($actionKey);

        return $this->makeRuleData(
            actionKey: $actionKey,
            points: $baseRule->points,
            everyCount: $baseRule->every_count,
            isActive: $baseRule->is_active,
            isSpecialOffer: false,
            endsAt: null,
            description: $baseRule->description,
        );
    }

    /**
     * @return array{
     *     action_key: string,
     *     action_label: string,
     *     points:int,
     *     every_count:int,
     *     is_active:bool,
     *     is_special_offer:bool,
     *     rule_label:string,
     *     ends_at:?CarbonInterface,
     *     ends_at_formatted:?string,
     *     description:?string
     * }
     */
    public function getBaseRuleData(string $actionKey): array
    {
        $baseRule = $this->getBaseRule($actionKey);

        return $this->makeRuleData(
            actionKey: $actionKey,
            points: $baseRule->points,
            everyCount: $baseRule->every_count,
            isActive: $baseRule->is_active,
            isSpecialOffer: false,
            endsAt: null,
            description: $baseRule->description,
        );
    }

    /**
     * @return array<int, array{
     *     action_key: string,
     *     action_label: string,
     *     base_rule: array{
     *         action_key: string,
     *         action_label: string,
     *         points:int,
     *         every_count:int,
     *         is_active:bool,
     *         is_special_offer:bool,
     *         rule_label:string,
     *         ends_at:?CarbonInterface,
     *         ends_at_formatted:?string,
     *         description:?string
     *     },
     *     effective_rule: array{
     *         action_key: string,
     *         action_label: string,
     *         points:int,
     *         every_count:int,
     *         is_active:bool,
     *         is_special_offer:bool,
     *         rule_label:string,
     *         ends_at:?CarbonInterface,
     *         ends_at_formatted:?string,
     *         description:?string
     *     }
     * }>
     */
    public function getActionConfigurations(): array
    {
        return collect(RomantauschBaxxSpecialOffer::allowedActionKeys())
            ->map(fn (string $actionKey): array => [
                'action_key' => $actionKey,
                'action_label' => RomantauschBaxxSpecialOffer::actionLabel($actionKey),
                'base_rule' => $this->getBaseRuleData($actionKey),
                'effective_rule' => $this->getEffectiveRule($actionKey),
            ])
            ->values()
            ->all();
    }

    public function getActiveSpecialOffer(string $actionKey): ?RomantauschBaxxSpecialOffer
    {
        return RomantauschBaxxSpecialOffer::query()
            ->forActionKey($actionKey)
            ->currentlyActive()
            ->orderByDesc('ends_at')
            ->first();
    }

    public function awardForNewOffers(int $userId, int $newOfferCount): int
    {
        return $this->awardByRule(
            userId: $userId,
            actionKey: 'romantausch_offer',
            newActionCount: $newOfferCount,
            resolveTotalCount: fn (): int => (int) BookOffer::query()
                ->where('user_id', $userId)
                ->count(),
        );
    }

    public function awardForNewRequests(int $userId, int $newRequestCount = 1): int
    {
        return $this->awardByRule(
            userId: $userId,
            actionKey: 'romantausch_request',
            newActionCount: $newRequestCount,
            resolveTotalCount: fn (): int => (int) BookRequest::query()
                ->where('user_id', $userId)
                ->count(),
        );
    }

    /**
     * @return array{offer_user_points: int, request_user_points: int}
     */
    public function awardForCompletedSwap(BookSwap $swap): array
    {
        return [
            'offer_user_points' => $this->awardByRule(
                userId: $swap->offer->user_id,
                actionKey: 'romantausch_swap_complete',
                newActionCount: 1,
                resolveTotalCount: fn (): int => $this->completedOfferSwapCount($swap->offer->user_id),
            ),
            'request_user_points' => $this->awardByRule(
                userId: $swap->request->user_id,
                actionKey: 'romantausch_swap_complete',
                newActionCount: 1,
                resolveTotalCount: fn (): int => $this->completedRequestSwapCount($swap->request->user_id),
            ),
        ];
    }

    private function completedOfferSwapCount(int $userId): int
    {
        return (int) BookSwap::query()
            ->whereNotNull('completed_at')
            ->whereHas('offer', fn ($query) => $query->where('user_id', $userId))
            ->count();
    }

    private function completedRequestSwapCount(int $userId): int
    {
        return (int) BookSwap::query()
            ->whereNotNull('completed_at')
            ->whereHas('request', fn ($query) => $query->where('user_id', $userId))
            ->count();
    }

    private function awardByRule(int $userId, string $actionKey, int $newActionCount, Closure $resolveTotalCount): int
    {
        if ($newActionCount < 1) {
            return 0;
        }

        return DB::transaction(function () use ($userId, $actionKey, $newActionCount, $resolveTotalCount): int {
            $resolvedCount = max(0, (int) $resolveTotalCount());
            $initialProcessedCount = max(0, $resolvedCount - $newActionCount);
            $progress = $this->lockProgress($userId, $actionKey, $initialProcessedCount);
            $processedCount = max($initialProcessedCount, max(0, $progress->processed_count));
            $currentCount = max($resolvedCount, $processedCount);
            $rule = $this->getEffectiveRule($actionKey);

            if (! $rule['is_active'] || $rule['points'] <= 0) {
                $this->markProcessedCount($progress, $currentCount);

                return 0;
            }

            $everyCount = max(1, $rule['every_count']);
            $thresholdCrossings = intdiv($currentCount, $everyCount) - intdiv($processedCount, $everyCount);

            if ($thresholdCrossings <= 0) {
                $this->markProcessedCount($progress, $currentCount);

                return 0;
            }

            $walletTeam = $this->resolveMembersWalletTeam($userId, $actionKey);
            $awardedPoints = $thresholdCrossings * $rule['points'];

            UserPoint::query()->create([
                'user_id' => $userId,
                'team_id' => $walletTeam->id,
                'points' => $awardedPoints,
            ]);

            $this->markProcessedCount($progress, $currentCount);

            return $awardedPoints;
        });
    }

    private function getBaseRule(string $actionKey): BaxxEarningRule
    {
        return BaxxEarningRule::query()->firstOrCreate(
            ['action_key' => $actionKey],
            RomantauschBaxxSpecialOffer::defaultRuleDefinition($actionKey),
        );
    }

    private function lockProgress(int $userId, string $actionKey, int $initialProcessedCount): BaxxEarningProgress
    {
        $timestamp = now();

        BaxxEarningProgress::query()->upsert(
            [[
                'user_id' => $userId,
                'action_key' => $actionKey,
                'processed_count' => $initialProcessedCount,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]],
            ['user_id', 'action_key'],
            ['updated_at']
        );

        return BaxxEarningProgress::query()
            ->where('user_id', $userId)
            ->where('action_key', $actionKey)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function markProcessedCount(BaxxEarningProgress $progress, int $processedCount): void
    {
        if ($processedCount <= $progress->processed_count) {
            return;
        }

        $progress->update([
            'processed_count' => $processedCount,
        ]);
    }

    private function resolveMembersWalletTeam(int $userId, string $actionKey): Team
    {
        $walletTeam = Team::membersTeam();

        if ($walletTeam) {
            return $walletTeam;
        }

        $context = [
            'user_id' => $userId,
            'action_key' => $actionKey,
            'members_team_id' => $walletTeam?->id,
        ];

        Log::critical('Romantausch-Baxx konnten nicht vergeben werden, weil das Mitglieder-Team fehlt.', $context);

        throw new LogicException(
            sprintf(
                'Das Mitglieder-Team fehlt. Romantausch-Baxx können nicht vergeben werden (user_id: %d, action_key: %s).',
                $userId,
                $actionKey,
            )
        );
    }

    /**
     * @return array{
     *     action_key: string,
     *     action_label: string,
     *     points:int,
     *     every_count:int,
     *     is_active:bool,
     *     is_special_offer:bool,
     *     rule_label:string,
     *     ends_at:?CarbonInterface,
     *     ends_at_formatted:?string,
     *     description:?string
     * }
     */
    private function makeRuleData(
        string $actionKey,
        int $points,
        int $everyCount,
        bool $isActive,
        bool $isSpecialOffer,
        ?CarbonInterface $endsAt,
        ?string $description,
    ): array {
        $everyCount = max(1, $everyCount);

        return [
            'action_key' => $actionKey,
            'action_label' => RomantauschBaxxSpecialOffer::actionLabel($actionKey),
            'points' => $points,
            'every_count' => $everyCount,
            'is_active' => $isActive,
            'is_special_offer' => $isSpecialOffer,
            'rule_label' => $this->formatRuleLabel($points, $everyCount),
            'ends_at' => $endsAt,
            'ends_at_formatted' => $endsAt?->copy()->timezone(config('app.timezone'))->format('d.m.Y, H:i'),
            'description' => $description,
        ];
    }

    private function formatRuleLabel(int $points, int $everyCount): string
    {
        if ($everyCount === 1) {
            return $points.' Baxx pro Auslöser';
        }

        return $points.' Baxx pro '.$everyCount.' Auslöser';
    }
}