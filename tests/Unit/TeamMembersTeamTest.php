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

        $this->assertNotNull($team);
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

    public function test_members_team_cache_cleared_on_team_update(): void
    {
        $team = Team::membersTeam();
        $team->update(['description' => 'updated']);

        DB::enableQueryLog();
        $updatedTeam = Team::membersTeam();

        $this->assertCount(1, DB::getQueryLog());
        $this->assertSame('updated', $updatedTeam->description);

        DB::flushQueryLog();
        Team::membersTeam();

        $this->assertCount(0, DB::getQueryLog());
    }

    public function test_members_team_cache_cleared_on_team_delete(): void
    {
        $team = Team::membersTeam();
        $team->delete();
        Team::factory()->create(['name' => 'Mitglieder']);

        DB::enableQueryLog();
        $newTeam = Team::membersTeam();

        $this->assertCount(1, DB::getQueryLog());
        $this->assertNotSame($team->id, $newTeam->id);
    }
}
