<?php

namespace App\Services\Maddraxikon;

final readonly class MaddraxikonIdentity
{
    public function __construct(
        public string $oauthSubject,
        public int $wikiUserId,
        public string $wikiUsername,
    ) {}
}
