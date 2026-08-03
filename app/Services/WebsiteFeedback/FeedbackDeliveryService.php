<?php

namespace App\Services\WebsiteFeedback;

use App\Data\WebsiteFeedback\WebsiteFeedbackData;
use App\Mail\WebsiteFeedbackReceived;
use Illuminate\Support\Facades\Mail;

class FeedbackDeliveryService
{
    /**
     * @param  array<int, string>  $recipientEmails
     */
    public function queue(WebsiteFeedbackData $feedback, array $recipientEmails): void
    {
        foreach ($recipientEmails as $email) {
            Mail::to($email)->queue(new WebsiteFeedbackReceived($feedback));
        }
    }
}
