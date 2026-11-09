<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUserWithRole;

trait DatabaseMaintenanceControllerTestFixtures
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    private string $storageRoot;
}
