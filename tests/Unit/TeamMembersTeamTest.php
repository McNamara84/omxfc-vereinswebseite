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

    public function test_members_team_cache_not_cleared_when_description_updated(): void
    {
        $team = Team::membersTeam();
        $team->update(['description' => 'updated']);

        DB::enableQueryLog();
        $cachedTeam = Team::membersTeam();

        $this->assertCount(0, DB::getQueryLog());
        $this->assertSame('updated', $cachedTeam->description);
    }

    public function test_members_team_cache_cleared_when_name_changes(): void
    {
        $team = Team::membersTeam();
        $team->update(['name' => 'Mitglieder-renamed']);

        DB::enableQueryLog();
        $renamedTeam = Team::membersTeam();

        $this->assertCount(1, DB::getQueryLog());
        $this->assertSame('Mitglieder-renamed', $renamedTeam->name);
    }

    public function test_members_team_returns_null_when_not_found(): void
    {
        $team = Team::membersTeam();
        $team->delete();

        DB::enableQueryLog();
        $firstCall = Team::membersTeam();
        $secondCall = Team::membersTeam();
        $thirdCall = Team::membersTeam();

        $this->assertNull($firstCall);
        $this->assertNull($secondCall);
        $this->assertNull($thirdCall);
        $this->assertCount(3, DB::getQueryLog());
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
