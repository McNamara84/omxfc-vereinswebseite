<?php

namespace App\Livewire\Profile;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Mary\Traits\Toast;

class UpdateReviewNotificationForm extends Component
{
    use Toast;

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

        $this->toast(
            type: 'success',
            title: __('Gespeichert.'),
            position: 'toast-bottom toast-end',
            icon: 'o-check-circle',
            timeout: 3000,
        );
    }

    public function render()
    {
        return view('profile.update-review-notification-form');
    }
}
