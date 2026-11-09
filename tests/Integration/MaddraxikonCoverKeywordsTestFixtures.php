<?php

declare(strict_types=1);

namespace Tests\Integration;

trait MaddraxikonCoverKeywordsTestFixtures
{
    private function page(string $title, array $keywords = []): array
    {
        return ['namespace' => 6, 'exists' => '1', 'fulltext' => $title, 'printouts' => ['Schlagworte' => $keywords]];
    }
}
