<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Services\KompendiumService;
use App\Services\MaddraxDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait KompendiumServiceTestFixtures
{
    use RefreshDatabase;

    private KompendiumService $service;

    private MaddraxDataService $maddraxDataService;
}
