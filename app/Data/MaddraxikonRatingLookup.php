<?php

namespace App\Data;

final readonly class MaddraxikonRatingLookup
{
    public function __construct(
        public int $wikiUserId,
        public int $pageId,
    ) {}

    public function key(): string
    {
        return self::makeKey($this->wikiUserId, $this->pageId);
    }

    public static function makeKey(int $wikiUserId, int $pageId): string
    {
        return $wikiUserId.':'.$pageId;
    }
}
