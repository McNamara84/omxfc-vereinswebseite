<?php

namespace App\Services\Maddraxikon;

use App\Models\MaddraxikonContribution;
use App\Models\MaddraxikonRewardPolicy;
use App\Models\MaddraxikonRewardPolicyTier;
use Illuminate\Support\Collection;
use LogicException;

final class MaddraxikonEditSessionRewardCalculator
{
    /**
     * @param  Collection<int, MaddraxikonContribution>  $contributions
     */
    public function calculate(
        Collection $contributions,
        MaddraxikonRewardPolicy $policy,
    ): MaddraxikonEditSessionRewardCalculation {
        if ($contributions->isEmpty()) {
            throw new LogicException('Eine leere Bearbeitungssitzung kann nicht bewertet werden.');
        }

        $ordered = $contributions
            ->sortBy([
                ['occurred_at_epoch', 'asc'],
                ['revision_id', 'asc'],
            ])
            ->values();
        $first = $ordered->first();
        $last = $ordered->last();

        if ($ordered->contains(
            fn (MaddraxikonContribution $contribution): bool => (
                $contribution->old_size === null
                || $contribution->new_size === null
            )
        )) {
            throw new LogicException('revision_size_unavailable');
        }

        $startSize = max(0, (int) $first->old_size);
        $endSize = max(0, (int) $last->new_size);
        $addedBytes = max(0, (int) $ordered->sum(
            fn (MaddraxikonContribution $contribution): int => (
                (int) $contribution->new_size - (int) $contribution->old_size
            )
        ));

        if (! $policy->edit_sessions_enabled) {
            return new MaddraxikonEditSessionRewardCalculation(
                $startSize,
                $endSize,
                $addedBytes,
                null,
                0,
                'edit_sessions_disabled',
            );
        }

        $tiers = $policy->relationLoaded('tiers')
            ? $policy->tiers
            : $policy->tiers()->get();
        $tier = $tiers
            ->where('minimum_added_bytes', '<=', $addedBytes)
            ->sortByDesc('minimum_added_bytes')
            ->first();

        if (! $tier instanceof MaddraxikonRewardPolicyTier) {
            return new MaddraxikonEditSessionRewardCalculation(
                $startSize,
                $endSize,
                $addedBytes,
                null,
                0,
                'below_minimum_edit_size',
            );
        }

        $candidatePoints = max(0, (int) $tier->points);

        return new MaddraxikonEditSessionRewardCalculation(
            $startSize,
            $endSize,
            $addedBytes,
            $tier,
            $candidatePoints,
            $candidatePoints > 0 ? null : 'policy_has_no_points',
        );
    }
}
