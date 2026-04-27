<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

class ChronikPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_chronik_page_shows_timeline_sections_and_single_main_heading(): void
    {
        $response = $this->withoutVite()->get('/chronik');

        $response->assertOk();
        $response->assertSeeText('Chronik des Offiziellen MADDRAX Fanclub e. V.');
        $response->assertSeeText('Meilensteine des Vereins');
        $response->assertSeeText('Was die Chronik zeigt');
        $response->assertSeeText('Heute');

        $crawler = new Crawler($response->getContent());
        $this->assertCount(1, $crawler->filter('h1'));
    }
}