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

        $this->post('/treffen/umleiten', ['meeting' => 'unknown'])
            ->assertForbidden();
    }

    public function test_meetings_page_returns_meeting_data(): void
    {
        Carbon::setTestNow('2025-03-15');
        $this->actingAs($this->actingMember());

        $response = $this->get('/treffen');
        $response->assertOk();

        $meetings = $response->viewData('meetings');
        $this->assertCount(4, $meetings);
        $this->assertSame('AG Maddraxikon', $meetings[0]['name']);
        $this->assertTrue($meetings[0]['next']->isFuture());
    }

    public function test_meetings_page_handles_special_case_without_date(): void
    {
        Carbon::setTestNow('2025-03-15');
        $this->actingAs($this->actingMember());

        $meetings = $this->get('/treffen')->viewData('meetings');

        $last = end($meetings);
        $this->assertSame('CHATDRAX 2.0 - Der MADDRAX-Online-Stammtisch', $last['name']);
        $this->assertNull($last['next']);
    }

    public function test_meetings_page_computes_dates_for_all_meetings(): void
    {
        Carbon::setTestNow('2025-03-15');
        $this->actingAs($this->actingMember());

        $meetings = $this->get('/treffen')->viewData('meetings');

        $this->assertTrue($meetings[0]['next']->equalTo(Carbon::parse('third monday of this month')));
        $this->assertTrue($meetings[1]['next']->equalTo(Carbon::parse('second wednesday of next month')));
        $this->assertTrue($meetings[2]['next']->equalTo(Carbon::parse('first wednesday of next month')));
    }

    public function test_valid_meeting_redirects_to_configured_zoom_url(): void
    {
        putenv('ZOOM_LINK_MADDRAXIKON=https://example.com/zoom');
        $_ENV['ZOOM_LINK_MADDRAXIKON'] = 'https://example.com/zoom';
        $_SERVER['ZOOM_LINK_MADDRAXIKON'] = 'https://example.com/zoom';

        $this->actingAs($this->actingMember());

        $this->post('/treffen/umleiten', ['meeting' => 'maddraxikon'])
            ->assertRedirect('https://example.com/zoom');
    }
}
