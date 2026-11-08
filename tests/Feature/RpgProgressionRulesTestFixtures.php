<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\RpgCharacterAdvancementEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\RpgProgressionFixtures;

trait RpgProgressionRulesTestFixtures
{
    use RefreshDatabase, RpgProgressionFixtures;

    private function evaluate(array $operations, ?array $payload = null): array
    {
        return app(RpgCharacterAdvancementEvaluator::class)->evaluate($payload ?? $this->progressionPayload(), $operations);
    }
}
