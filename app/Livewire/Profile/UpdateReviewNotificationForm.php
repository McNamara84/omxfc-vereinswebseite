<?php

namespace App\Livewire\Profile;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class UpdateReviewNotificationForm extends Component
{
    public bool $notifyNewReview = false;

    public function mount(): void
    {
        $this->notifyNewReview = Auth::user()->notify_new_review;
    }

    public function save(): void
    {
        $user = Auth::user();
        $user->notify_new_review = $this->notifyNewReview;
        $user->save();

        $this->dispatch('saved');
    }

    public function render()
    {
        return view('profile.update-review-notification-form');
    }
}