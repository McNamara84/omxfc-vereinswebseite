<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\DomCrawler\Crawler;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class MeetingsPageTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    public function test_meetings_page_shows_context_and_correct_meeting_keys(): void
    {
        Carbon::setTestNow('2025-03-15');
        $this->actingAs($this->actingMember());

        $response = $this->withoutVite()->get('/treffen');

        $response->assertOk();
        $response->assertSeeText('Meetings');
        $response->assertSeeText('Regelmäßige Termine');
        $response->assertSeeText('Wie die Termine laufen');

        $crawler = new Crawler($response->getContent());
        $meetingKeys = $crawler->filter('input[name="meeting"]')->each(
            fn (Crawler $node) => $node->attr('value')
        );

        $this->assertSame(['maddraxikon', 'fanhoerbuch', 'mapdrax', 'stammtisch'], $meetingKeys);
    }
}