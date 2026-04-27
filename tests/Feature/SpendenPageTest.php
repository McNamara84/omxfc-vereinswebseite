<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

class SpendenPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_spenden_page_shows_support_sections_and_single_main_heading(): void
    {
        $response = $this->withoutVite()->get('/spenden');

        $response->assertOk();
        $response->assertSeeText('Spenden');
        $response->assertSeeText('Was deine Spende ermöglicht');
        $response->assertSeeText('Direkt per PayPal');
        $response->assertSeeText('kassenwart@maddrax-fanclub.de');

        $crawler = new Crawler($response->getContent());
        $this->assertCount(1, $crawler->filter('h1'));
    }
}