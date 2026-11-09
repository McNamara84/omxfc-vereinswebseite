<?php

declare(strict_types=1);

namespace Tests\Integration\Logging;

use Illuminate\Foundation\Testing\RefreshDatabase;

trait RedactSensitiveLogContextTestFixtures
{
    use RefreshDatabase;

    private string $logPath;
}
