<?php

namespace App\Data\WebsiteFeedback;

use App\Enums\WebsiteFeedbackCategory;
use Carbon\CarbonImmutable;

final readonly class WebsiteFeedbackData
{
    public function __construct(
        public WebsiteFeedbackCategory $category,
        public string $message,
        public string $pageTitle,
        public string $pageUrl,
        public CarbonImmutable $submittedAt,
        public ?string $reporterName = null,
        public ?string $reporterEmail = null,
    ) {}

    public function isAnonymous(): bool
    {
        return $this->reporterName === null && $this->reporterEmail === null;
    }
}
