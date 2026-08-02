<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\WebsiteFeedback\FeedbackSubmissionThrottle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackSubmissionThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_limits_successful_submissions_per_user_for_one_hour(): void
    {
        config(['feedback.rate_limit_per_hour' => 1]);
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $throttle = app(FeedbackSubmissionThrottle::class);

        $this->assertFalse($throttle->tooManyAttempts($user));

        $throttle->record($user);

        $this->assertTrue($throttle->tooManyAttempts($user));
        $this->assertFalse($throttle->tooManyAttempts($otherUser));
        $this->assertSame('website-feedback:user:'.$user->id, $throttle->key($user));
    }
}
