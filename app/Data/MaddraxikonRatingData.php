<?php

namespace App\Data;

use Carbon\CarbonImmutable;

final readonly class MaddraxikonRatingData
{
    public function __construct(
        public int $wikiUserId,
        public int $pageId,
        public int $rating,
        public ?CarbonImmutable $votedAt,
    ) {}

    public function key(): string
    {
        return MaddraxikonRatingLookup::makeKey($this->wikiUserId, $this->pageId);
    }
}
