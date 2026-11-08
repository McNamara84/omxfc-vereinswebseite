<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait MaddraxikonStreakUiTestFixtures
{
    use RefreshDatabase;

    private User $member;
}
