<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

class TerminePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_termine_page_shows_calendar_sections_and_single_main_heading(): void
    {
        $response = $this->withoutVite()->get('/termine');

        $response->assertOk();
        $response->assertSeeText('Termine');
        $response->assertSeeText('Was im Kalender landet');
        $response->assertSeeText('Vereinskalender');
        $response->assertSeeText('Kalender abonnieren');
        $response->assertSee('href="'.route('arbeitsgruppen').'"', false);
        $response->assertDontSee('href="'.route('arbeitsgruppen.index').'"', false);

        $crawler = new Crawler($response->getContent());
        $this->assertCount(1, $crawler->filter('h1'));
    }
}