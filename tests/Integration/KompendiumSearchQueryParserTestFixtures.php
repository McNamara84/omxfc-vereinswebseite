<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\KompendiumSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait KompendiumSearchQueryParserTestFixtures
{
    use RefreshDatabase;

    private KompendiumSearchService $service;
}
