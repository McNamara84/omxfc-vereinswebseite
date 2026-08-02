<?php

namespace App\Services\WebsiteFeedback;

use App\Models\User;
use Illuminate\Cache\RateLimiter;

class FeedbackSubmissionThrottle
{
    public function __construct(private readonly RateLimiter $limiter) {}

    public function tooManyAttempts(User $user): bool
    {
        return $this->limiter->tooManyAttempts(
            $this->key($user),
            max(1, (int) config('feedback.rate_limit_per_hour', 5)),
        );
    }

    public function record(User $user): void
    {
        $this->limiter->hit($this->key($user), 3600);
    }

    public function key(User $user): string
    {
        return 'website-feedback:user:'.$user->getKey();
    }
}
