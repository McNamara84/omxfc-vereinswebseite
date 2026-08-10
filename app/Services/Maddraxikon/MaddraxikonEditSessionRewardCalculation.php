<?php

namespace App\Services\Maddraxikon;

use App\Models\MaddraxikonRewardPolicyTier;

final readonly class MaddraxikonEditSessionRewardCalculation
{
    public function __construct(
        public int $startSize,
        public int $endSize,
        public int $addedBytes,
        public ?MaddraxikonRewardPolicyTier $tier,
        public int $candidatePoints,
        public ?string $statusReason,
    ) {}
}
