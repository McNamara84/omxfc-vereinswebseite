<?php

namespace Tests\Unit;

use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TeamMembersTeamTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Team::clearMembersTeamCache();
    }

    public function test_members_team_returns_correct_team(): void
    {
        $team = Team::membersTeam();

        $this->assertSame('Mitglieder', $team->name);
    }

    public function test_members_team_is_cached_after_first_call(): void
    {
        DB::enableQueryLog();

        Team::membersTeam();
        Team::membersTeam();

        $this->assertCount(1, DB::getQueryLog());
    }

    public function test_clear_members_team_cache_resets_cache(): void
    {
        DB::enableQueryLog();

        Team::membersTeam();
        Team::clearMembersTeamCache();
        Team::membersTeam();

        $this->assertCount(2, DB::getQueryLog());
    }
}
