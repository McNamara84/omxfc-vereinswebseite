<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\RewardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUserWithRole;

trait RewardServiceTestFixtures
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    private RewardService $service;
}
