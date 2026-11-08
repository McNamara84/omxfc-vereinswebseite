<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\ErrorReporting\ErrorNotificationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait ErrorNotificationPolicyTestFixtures
{
    use RefreshDatabase;

    private ErrorNotificationPolicy $policy;
}
