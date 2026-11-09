<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\DomCrawler\Crawler;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CreatesUserWithRole::class, RefreshDatabase::class);

test('meetings page shows context and correct meeting keys', function (): void {
    Carbon::setTestNow('2025-03-15');
    try {
        $this->actingAs($this->actingMember());
        $response = $this->withoutVite()->get('/treffen');
        $response->assertOk();
        $response->assertSeeText('Meetings');
        $response->assertSeeText('Regelmäßige Termine');
        $response->assertSeeText('Wie die Termine laufen');
        $crawler = new Crawler($response->getContent());
        $meetingKeys = $crawler->filter('input[name="meeting"]')->each(fn (Crawler $node) => $node->attr('value'));
        expect($meetingKeys)->toBe(['maddraxikon', 'fanhoerbuch', 'mapdrax', 'stammtisch']);
    } finally {
        Carbon::setTestNow();
    }
});
