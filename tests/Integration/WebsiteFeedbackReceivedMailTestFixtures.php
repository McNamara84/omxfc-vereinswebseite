<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Data\WebsiteFeedback\WebsiteFeedbackData;
use App\Enums\WebsiteFeedbackCategory;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait WebsiteFeedbackReceivedMailTestFixtures
{
    use RefreshDatabase;

    private function feedback(string $message = 'Bitte ergänzt eine bessere Navigation.', ?string $reporterName = null, ?string $reporterEmail = null): WebsiteFeedbackData
    {
        return new WebsiteFeedbackData(category: WebsiteFeedbackCategory::Idea, message: $message, pageTitle: 'Mitgliederbereich', pageUrl: 'https://maddrax-fanclub.de/dashboard', submittedAt: new CarbonImmutable('2026-08-02 12:00:00', 'Europe/Berlin'), reporterName: $reporterName, reporterEmail: $reporterEmail);
    }
}
