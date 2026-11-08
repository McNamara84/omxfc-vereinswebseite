<?php

declare(strict_types=1);

namespace Tests\Integration\Services\Romantausch;

use App\Services\Romantausch\BundleService;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait BundleServiceTestFixtures
{
    use RefreshDatabase;

    private BundleService $service;
}
