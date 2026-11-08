<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

trait TeamMembersTeamTestFixtures
{
    use RefreshDatabase;

    private function assertTeamsQueryCount(int $expectedCount): void
    {
        $teamsQueries = array_filter(DB::getQueryLog(), static fn (array $entry): bool => preg_match('/\bfrom\s+["`]?teams["`]?\b/i', $entry['query']) === 1);
        expect($teamsQueries)->toHaveCount($expectedCount);
    }
}
