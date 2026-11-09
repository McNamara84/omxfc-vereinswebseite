<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;

trait IndexRomaneCommandTestFixtures
{
    use RefreshDatabase;

    private string $testStoragePath;
}
