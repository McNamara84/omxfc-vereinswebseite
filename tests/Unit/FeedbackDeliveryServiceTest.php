<?php

namespace Tests\Unit;

use App\Data\WebsiteFeedback\WebsiteFeedbackData;
use App\Enums\WebsiteFeedbackCategory;
use App\Mail\WebsiteFeedbackReceived;
use App\Services\WebsiteFeedback\FeedbackDeliveryService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FeedbackDeliveryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_one_separately_addressed_mail_per_recipient(): void
    {
        Mail::fake();
        $feedback = new WebsiteFeedbackData(
            category: WebsiteFeedbackCategory::Other,
            message: 'Eine ausreichend lange Rückmeldung.',
            pageTitle: 'Dashboard',
            pageUrl: 'https://maddrax-fanclub.de/dashboard',
            submittedAt: CarbonImmutable::now(),
        );

        app(FeedbackDeliveryService::class)->queue($feedback, [
            'admin@example.com',
            'vorstand@example.com',
        ]);

        Mail::assertQueuedCount(2);
        Mail::assertQueued(WebsiteFeedbackReceived::class, function (WebsiteFeedbackReceived $mail) use ($feedback): bool {
            return $mail->hasTo('admin@example.com')
                && ! $mail->hasTo('vorstand@example.com')
                && $mail->feedback === $feedback;
        });
        Mail::assertQueued(WebsiteFeedbackReceived::class, function (WebsiteFeedbackReceived $mail) use ($feedback): bool {
            return $mail->hasTo('vorstand@example.com')
                && ! $mail->hasTo('admin@example.com')
                && $mail->feedback === $feedback;
        });
    }
}
