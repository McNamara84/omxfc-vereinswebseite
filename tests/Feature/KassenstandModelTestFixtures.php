<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait KassenstandModelTestFixtures
{
    use RefreshDatabase;

    private function getTeam(): Team
    {
        return Team::membersTeam();
    }
}
