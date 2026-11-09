<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;

trait VeranstaltungsBaxxMariaDbConcurrencyTestFixtures
{
    use RefreshDatabase;

    private bool $committed = false;
}
