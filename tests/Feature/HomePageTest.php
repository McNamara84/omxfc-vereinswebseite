<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_renders_single_main_heading_and_primary_sections(): void
    {
        $response = $this->withoutVite()->get('/');

        $response->assertOk();
        $response->assertSeeText('Willkommen beim Offiziellen MADDRAX Fanclub e. V.!');
        $response->assertSeeText('Aktuelle Projekte');
        $response->assertSeeText('Vorteile einer Mitgliedschaft');
        $response->assertSeeText('Letzte Rezensionen');
        $response->assertSeeText('Mitglied werden');
        $response->assertSeeText('Fantreffen 2026');

        $crawler = new Crawler($response->getContent());
        $this->assertCount(1, $crawler->filter('h1'));
    }

    public function test_home_page_shows_key_metrics_and_gallery(): void
    {
        $response = $this->withoutVite()->get('/');

        $response->assertOk();
        $response->assertSee('id="gallery"', false);
        $response->assertSeeText('Aktive Mitglieder');
        $response->assertSeeText('Rezensionen');
        $response->assertSeeText('Community im echten Leben');
    }
}