<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Enums\Role;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

trait FeedbackRecipientResolverTestFixtures
{
    use RefreshDatabase;

    private function removeSeededManagement(Team $team): void
    {
        DB::table('team_user')->where('team_id', $team->id)->whereIn('role', [Role::Admin->value, Role::Vorstand->value])->delete();
    }
}
