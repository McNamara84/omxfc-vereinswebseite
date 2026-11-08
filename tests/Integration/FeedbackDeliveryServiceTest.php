<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Data\WebsiteFeedback\WebsiteFeedbackData;
use App\Enums\WebsiteFeedbackCategory;
use App\Mail\WebsiteFeedbackReceived;
use App\Services\WebsiteFeedback\FeedbackDeliveryService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('it queues one separately addressed mail per recipient', function (): void {
    Mail::fake();
    $feedback = new WebsiteFeedbackData(category: WebsiteFeedbackCategory::Other, message: 'Eine ausreichend lange Rückmeldung.', pageTitle: 'Dashboard', pageUrl: 'https://maddrax-fanclub.de/dashboard', submittedAt: CarbonImmutable::now());
    app(FeedbackDeliveryService::class)->queue($feedback, ['admin@example.com', 'vorstand@example.com']);
    Mail::assertQueuedCount(2);
    Mail::assertQueued(WebsiteFeedbackReceived::class, fn (WebsiteFeedbackReceived $mail): bool => $mail->hasTo('admin@example.com') && ! $mail->hasTo('vorstand@example.com') && $mail->feedback === $feedback);
    Mail::assertQueued(WebsiteFeedbackReceived::class, fn (WebsiteFeedbackReceived $mail): bool => $mail->hasTo('vorstand@example.com') && ! $mail->hasTo('admin@example.com') && $mail->feedback === $feedback);
});
