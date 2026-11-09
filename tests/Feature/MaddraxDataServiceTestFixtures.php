<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUserWithRole;

trait MaddraxDataServiceTestFixtures
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    private string $testStoragePath;
}
