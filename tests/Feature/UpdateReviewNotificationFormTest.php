<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Profile\UpdateReviewNotificationForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('current notification setting is available', function (): void {
    $this->actingAs($user = User::factory()->create(['notify_new_review' => true]));
    Livewire::test(UpdateReviewNotificationForm::class)->assertSet('notifyNewReview', true)->assertViewIs('profile.update-review-notification-form');
});

test('review notification can be enabled', function (): void {
    $this->actingAs($user = User::factory()->create(['notify_new_review' => false]));
    Livewire::test(UpdateReviewNotificationForm::class)->set('notifyNewReview', true)->call('save')->assertHasNoErrors();
    expect($user->fresh()->notify_new_review)->toBeTrue();
});

test('review notification can be disabled', function (): void {
    $this->actingAs($user = User::factory()->create(['notify_new_review' => true]));
    Livewire::test(UpdateReviewNotificationForm::class)->set('notifyNewReview', false)->call('save')->assertHasNoErrors();
    expect($user->fresh()->notify_new_review)->toBeFalse();
});
