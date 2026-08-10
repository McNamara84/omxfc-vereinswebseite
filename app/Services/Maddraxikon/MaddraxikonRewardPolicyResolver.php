<?php

namespace App\Services\Maddraxikon;

use App\Models\MaddraxikonRewardPolicy;
use Carbon\CarbonImmutable;

final class MaddraxikonRewardPolicyResolver
{
    public function resolve(CarbonImmutable $activityAt): ?MaddraxikonRewardPolicy
    {
        return MaddraxikonRewardPolicy::query()
            ->with('tiers')
            ->effectiveAt($activityAt)
            ->latest('effective_from_epoch')
            ->first();
    }

    public function current(): ?MaddraxikonRewardPolicy
    {
        return $this->resolve(CarbonImmutable::now('UTC'));
    }

    public function next(): ?MaddraxikonRewardPolicy
    {
        return MaddraxikonRewardPolicy::query()
            ->with('tiers')
            ->published()
            ->where('effective_from_epoch', '>', CarbonImmutable::now('UTC')->getTimestamp())
            ->oldest('effective_from_epoch')
            ->first();
    }
}
