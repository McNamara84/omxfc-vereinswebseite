<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\KompendiumSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait KompendiumSearchServiceIndexExistsTestFixtures
{
    use RefreshDatabase;

    private string $testStoragePath;

    private KompendiumSearchService $service;
}
