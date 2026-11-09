<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait FanfictionAdminControllerTestFixtures
{
    use RefreshDatabase;

    private Team $memberTeam;

    private User $vorstand;

    private User $member;
}
