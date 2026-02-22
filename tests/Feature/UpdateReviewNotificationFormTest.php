<?php

namespace Tests\Feature;

use App\Livewire\Profile\UpdateReviewNotificationForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UpdateReviewNotificationFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_notification_setting_is_available(): void
    {
        $this->actingAs($user = User::factory()->create(['notify_new_review' => true]));

        Livewire::test(UpdateReviewNotificationForm::class)
            ->assertSet('notifyNewReview', true)
            ->assertViewIs('profile.update-review-notification-form');
    }

    public function test_review_notification_can_be_enabled(): void
    {
        $this->actingAs($user = User::factory()->create(['notify_new_review' => false]));

        Livewire::test(UpdateReviewNotificationForm::class)
            ->set('notifyNewReview', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue($user->fresh()->notify_new_review);
    }

    public function test_review_notification_can_be_disabled(): void
    {
        $this->actingAs($user = User::factory()->create(['notify_new_review' => true]));

        Livewire::test(UpdateReviewNotificationForm::class)
            ->set('notifyNewReview', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse($user->fresh()->notify_new_review);
    }
}
