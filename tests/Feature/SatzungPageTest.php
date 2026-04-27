<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

class SatzungPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_satzung_page_shows_single_main_heading_and_orientation_panel(): void
    {
        $response = $this->withoutVite()->get('/satzung');

        $response->assertOk();
        $response->assertSeeText('Satzung des Offiziellen MADDRAX Fanclub e.V.');
        $response->assertSeeText('Fassung vom 14. März 2026');
        $response->assertSeeText('Schnelle Orientierung');
        $response->assertSeeText('Wofür die Satzung wichtig ist');

        $crawler = new Crawler($response->getContent());
        $this->assertCount(1, $crawler->filter('h1'));
    }
}