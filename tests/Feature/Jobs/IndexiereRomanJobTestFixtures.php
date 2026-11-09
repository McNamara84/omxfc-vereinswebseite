<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait IndexiereRomanJobTestFixtures
{
    use RefreshDatabase;

    private User $admin;
}
