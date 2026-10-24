<?php

namespace App\Services\Baxx;

use Carbon\CarbonInterface;

final readonly class BaxxRuleData
{
    public int $everyCount;

    public function __construct(
        public string $actionKey,
        public int $points,
        int $everyCount,
        public bool $isActive,
        public bool $isSpecialOffer = false,
        public ?CarbonInterface $endsAt = null,
        public ?string $description = null,
    ) {
        $this->everyCount = max(1, $everyCount);
    }

    public function pointsAt(int $count): int
    {
        return $this->isActive && $this->points > 0 && $count > 0 && $count % $this->everyCount === 0
            ? $this->points : 0;
    }
}
