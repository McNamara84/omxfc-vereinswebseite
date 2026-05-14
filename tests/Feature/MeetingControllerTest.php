<?php

namespace Tests\Feature;

use App\Models\Meeting;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class MeetingControllerTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_unknown_meeting_is_forbidden(): void
    {
        $this->actingAs($this->actingMember());

        $response = $this->post('/treffen/umleiten', ['meeting' => 'unknown']);

        $response->assertForbidden();
        $this->assertInstanceOf(HttpException::class, $response->exception);
        $this->assertSame('Unbekanntes Meeting', $response->exception?->getMessage());
    }

    public function test_meetings_page_returns_seeded_meeting_data(): void
    {
        Carbon::setTestNow('2026-05-14 12:00');
        Meeting::query()->where('slug', 'maddraxikon')->update([
            'zoom_url' => 'https://example.com/zoom',
        ]);
        $this->actingAs($this->actingMember());

        $response = $this->get('/treffen');
        $response->assertOk()
            ->assertSeeText('AG Maddraxikon')
            ->assertSee('value="maddraxikon"', false);

        $meetings = $response->viewData('meetings');
        $this->assertCount(4, $meetings);
        $this->assertSame('AG Maddraxikon', $meetings->first()->title);
        $this->assertSame('Monatlich am 3. Montag', $meetings->first()->display_rhythm);
        $this->assertTrue($meetings->first()->next_occurrence->equalTo(Carbon::parse('2026-05-18 20:00')));
    }

    public function test_meetings_page_handles_note_only_without_date(): void
    {
        Carbon::setTestNow('2026-05-14 12:00');
        $this->actingAs($this->actingMember());

        $meetings = $this->get('/treffen')->viewData('meetings');

        $last = $meetings->last();
        $this->assertSame('CHATDRAX 2.0 - Der MADDRAX-Online-Stammtisch', $last->title);
        $this->assertSame('Jeden zweiten Dienstag nach einem Roman', $last->display_rhythm);
        $this->assertNull($last->next_occurrence);
    }

    public function test_meetings_page_computes_dates_for_all_meetings(): void
    {
        Carbon::setTestNow('2026-05-14 12:00');
        $this->actingAs($this->actingMember());

        $meetings = $this->get('/treffen')->viewData('meetings');

        $this->assertTrue($meetings[0]->next_occurrence->equalTo(Carbon::parse('2026-05-18 20:00')));
        $this->assertTrue($meetings[1]->next_occurrence->equalTo(Carbon::parse('2026-06-10 19:00')));
        $this->assertTrue($meetings[2]->next_occurrence->equalTo(Carbon::parse('2026-06-03 20:00')));
    }

    public function test_valid_meeting_redirects_to_configured_zoom_url(): void
    {
        Meeting::query()->where('slug', 'maddraxikon')->update([
            'zoom_url' => 'https://example.com/zoom',
        ]);

        $this->actingAs($this->actingMember());

        $this->post('/treffen/umleiten', ['meeting' => 'maddraxikon'])
            ->assertRedirect('https://example.com/zoom');
    }

    public function test_valid_meeting_redirects_to_configured_fallback_zoom_url(): void
    {
        config()->set('services.meetings.zoom_links.maddraxikon', 'https://example.com/fallback');

        Meeting::query()->where('slug', 'maddraxikon')->update([
            'zoom_url' => null,
        ]);

        $this->actingAs($this->actingMember());

        $this->post('/treffen/umleiten', ['meeting' => 'maddraxikon'])
            ->assertRedirect('https://example.com/fallback');
    }

    public function test_meetings_page_uses_configured_fallback_zoom_url(): void
    {
        config()->set('services.meetings.zoom_links.maddraxikon', 'https://example.com/fallback');
        Carbon::setTestNow('2026-05-14 12:00');

        Meeting::query()->where('slug', 'maddraxikon')->update([
            'zoom_url' => null,
        ]);

        $this->actingAs($this->actingMember());

        $this->get('/treffen')
            ->assertOk()
            ->assertSee('value="maddraxikon"', false)
            ->assertSeeText('Zoom-Meeting betreten');
    }

    public function test_meetings_page_hides_inactive_meetings(): void
    {
        Meeting::factory()->inactive()->create([
            'title' => 'Veraltetes Meeting',
            'slug' => 'veraltetes-meeting',
        ]);

        $this->actingAs($this->actingMember());

        $this->get('/treffen')
            ->assertOk()
            ->assertDontSeeText('Veraltetes Meeting');
    }

    public function test_meeting_without_configured_zoom_url_is_forbidden(): void
    {
        Meeting::query()->where('slug', 'maddraxikon')->update([
            'zoom_url' => null,
        ]);

        $this->actingAs($this->actingMember());

        $response = $this->post('/treffen/umleiten', ['meeting' => 'maddraxikon']);

        $response->assertForbidden();
        $this->assertInstanceOf(HttpException::class, $response->exception);
        $this->assertSame('Für dieses Treffen ist noch kein Zoom-Link hinterlegt.', $response->exception?->getMessage());
    }
}
