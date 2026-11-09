<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\MeetingScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait MeetingScheduleServiceTestFixtures
{
    use RefreshDatabase;

    protected MeetingScheduleService $service;
}
