<?php

namespace App\Services\Maddraxikon;

use Carbon\CarbonImmutable;

final readonly class OAuthAuthorizationAttempt
{
    public function __construct(
        public string $state,
        public string $codeVerifier,
        public string $codeChallenge,
        public string $consentVersion,
        public CarbonImmutable $consentedAt,
    ) {}
}
