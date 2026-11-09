<?php

declare(strict_types=1);

namespace Tests\Integration\Services\Romantausch;

use App\Services\Romantausch\SwapMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait SwapMatchingServiceTestFixtures
{
    use RefreshDatabase;

    private SwapMatchingService $service;
}
