<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;

trait ErrorFingerprintTestFixtures
{
    use RefreshDatabase;

    private function exceptionWithMessage(string $message): RuntimeException
    {
        return new RuntimeException($message);
    }
}
