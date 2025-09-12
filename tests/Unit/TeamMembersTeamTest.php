<?php

namespace Tests\Unit;

use App\Models\Team;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TeamMembersTeamTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget(Team::MEMBERS_TEAM_CACHE_KEY);
        $ref = new \ReflectionClass(Team::class);
        $prop = $ref->getProperty('membersTeamCache');
        $prop->setAccessible(true);
        $prop->setValue(null);
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
}
