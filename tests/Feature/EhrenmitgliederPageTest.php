<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

class EhrenmitgliederPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.testing_minimal_layout', true);
    }

    public function test_ehrenmitglieder_page_shows_single_main_heading_and_context_panels(): void
    {
        $response = $this->withoutVite()->get('/ehrenmitglieder');

        $response->assertOk();
        $response->assertSeeText('Ehrenmitglieder');
        $response->assertSeeText('Warum Ehrenmitglieder?');
        $response->assertSeeText('Bezug zur Community');
        $response->assertSeeText('Michael Edelbrock');
        $response->assertSeeText('Lucy Guth');
        $response->assertSee('alt="Michael Edelbrock"', false);
        $response->assertSee('width="1200"', false);
        $response->assertSee('height="1600"', false);

        $crawler = new Crawler($response->getContent());
        $this->assertCount(1, $crawler->filter('h1'));
    }
}