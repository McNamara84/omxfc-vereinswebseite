<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

class LegalInformationArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_impressum_page_shows_context_panels_and_single_main_heading(): void
    {
        $response = $this->withoutVite()->get('/impressum');

        $response->assertOk();
        $response->assertSeeText('Impressum');
        $response->assertSeeText('Angaben gemäß §5 TMG');
        $response->assertSeeText('Direkter Kontakt');

        $crawler = new Crawler($response->getContent());
        $this->assertCount(1, $crawler->filter('h1'));
    }

    public function test_datenschutz_page_shows_context_panels_and_single_main_heading(): void
    {
        $response = $this->withoutVite()->get('/datenschutz');

        $response->assertOk();
        $response->assertSeeText('Datenschutz');
        $response->assertSeeText('Datenschutzerklärung');
        $response->assertSeeText('Kontakt zum Verantwortlichen');
        $response->assertSeeText('Kurzüberblick');

        $crawler = new Crawler($response->getContent());
        $this->assertCount(1, $crawler->filter('h1'));
    }
}