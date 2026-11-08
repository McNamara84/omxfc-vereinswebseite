<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\User;
use App\Services\WebsiteFeedback\FeedbackSubmissionThrottle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('it limits successful submissions per user for one hour', function (): void {
    config(['feedback.rate_limit_per_hour' => 1]);
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $throttle = app(FeedbackSubmissionThrottle::class);
    expect($throttle->tooManyAttempts($user))->toBeFalse();
    $throttle->record($user);
    expect($throttle->tooManyAttempts($user))->toBeTrue()
        ->and($throttle->tooManyAttempts($otherUser))->toBeFalse()
        ->and($throttle->key($user))->toBe('website-feedback:user:'.$user->id);
});
