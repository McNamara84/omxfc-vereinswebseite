<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class MeetingControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingMember(): User
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => 'Mitglied']);
        return $user;
    }

    public function test_unknown_meeting_is_forbidden(): void
    {
        $this->actingAs($this->actingMember());

        $this->post('/meetings/redirect', ['meeting' => 'unknown'])
            ->assertForbidden();
    }

    public function test_meetings_page_returns_meeting_data(): void
    {
        Carbon::setTestNow('2025-03-15');
        $this->actingAs($this->actingMember());

        $response = $this->get('/meetings');
        $response->assertOk();

        $meetings = $response->viewData('meetings');
        $this->assertCount(4, $meetings);
        $this->assertSame('AG Maddraxikon', $meetings[0]['name']);
        $this->assertTrue($meetings[0]['next']->isFuture());
    }
}
