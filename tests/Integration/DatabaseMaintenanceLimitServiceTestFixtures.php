<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;

trait DatabaseMaintenanceLimitServiceTestFixtures
{
    use RefreshDatabase;

    private string $storageRoot;
}
