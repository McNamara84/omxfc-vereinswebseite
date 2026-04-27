<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

class ChangelogPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_changelog_page_shows_context_panels_and_single_main_heading(): void
    {
        $response = $this->withoutVite()->get('/changelog');

        $response->assertOk();
        $response->assertSeeText('Changelog');
        $response->assertSeeText('Änderungen an der Vereinswebseite');
        $response->assertSeeText('Wie du den Changelog liest');
        $response->assertSeeText('Wofür das nützlich ist');

        $crawler = new Crawler($response->getContent());
        $this->assertCount(1, $crawler->filter('h1'));
    }
}