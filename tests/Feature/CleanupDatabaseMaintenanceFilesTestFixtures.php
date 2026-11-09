<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;

trait CleanupDatabaseMaintenanceFilesTestFixtures
{
    use RefreshDatabase;

    private string $storageRoot;
}
