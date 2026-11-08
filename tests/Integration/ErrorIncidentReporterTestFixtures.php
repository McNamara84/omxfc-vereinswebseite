<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;

trait ErrorIncidentReporterTestFixtures
{
    use RefreshDatabase;

    private function sameOriginException(string $message): RuntimeException
    {
        return new RuntimeException($message);
    }
}
