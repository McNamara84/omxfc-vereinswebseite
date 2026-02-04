<?php

namespace Tests\Unit;

use App\Models\Team;
use App\Services\MembersTeamProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(MembersTeamProvider::class)]
class MembersTeamProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_team_when_exists(): void
    {
        $service = new MembersTeamProvider;
        $team = $service->getMembersTeamOrAbort();

        $this->assertInstanceOf(Team::class, $team);
        $this->assertSame('Mitglieder', $team->name);
    }

    public function test_redirects_when_team_missing(): void
    {
        Team::query()->where('name', 'Mitglieder')->delete();
        Team::clearMembersTeamCache();
        session()->start();

        $service = new MembersTeamProvider;

        try {
            $service->getMembersTeamOrAbort();
            $this->fail('Expected HttpResponseException not thrown');
        } catch (HttpResponseException $e) {
            $this->assertEquals(url('/'), $e->getResponse()->headers->get('Location'));
            $this->assertEquals('Team "Mitglieder" nicht gefunden.', $e->getResponse()->getSession()->get('error'));
        }
    }
}
