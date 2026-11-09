<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesErrorIncident;

trait SendErrorIncidentReportJobTestFixtures
{
    use CreatesErrorIncident;
    use RefreshDatabase;

    private function removeSeededAdmins(Team $team): void
    {
        DB::table('team_user')->where('team_id', $team->id)->where('role', Role::Admin->value)->delete();
    }
}
