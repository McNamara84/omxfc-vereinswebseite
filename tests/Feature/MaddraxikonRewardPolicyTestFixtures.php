<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MaddraxikonRewardPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait MaddraxikonRewardPolicyTestFixtures
{
    use RefreshDatabase;

    private function publishedPolicy(string $name, CarbonImmutable $effectiveFrom): MaddraxikonRewardPolicy
    {
        $policy = MaddraxikonRewardPolicy::factory()->create(['name' => $name, 'effective_from' => $effectiveFrom]);
        $policy->update(['status' => MaddraxikonRewardPolicy::STATUS_PUBLISHED, 'published_at' => $effectiveFrom->subMinute()]);

        return $policy->fresh();
    }
}
