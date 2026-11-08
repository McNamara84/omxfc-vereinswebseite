<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\Fanfiction;

trait FanfictionBildTagTestFixtures
{
    private function createFanfiction(string $content, array $photos = []): Fanfiction
    {
        $fanfiction = new Fanfiction(['content' => $content, 'title' => 'Testgeschichte']);
        // Set photos directly (model is not persisted, so no DB needed)
        $fanfiction->photos = $photos;

        return $fanfiction;
    }
}
