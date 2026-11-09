<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('home page renders single main heading and primary sections', function (): void {
    $response = $this->withoutVite()->get('/');
    $response->assertOk();
    $response->assertSeeText('Willkommen beim Offiziellen MADDRAX Fanclub e. V.!');
    $response->assertSeeText('Aktuelle Projekte');
    $response->assertSeeText('Vorteile einer Mitgliedschaft');
    $response->assertSeeText('Letzte Rezensionen');
    $response->assertSeeText('Mitglied werden');
    $response->assertSeeText('Aktuelle Veranstaltung');
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('h1'))->toHaveCount(1);
});

test('home page shows key metrics and gallery', function (): void {
    $response = $this->withoutVite()->get('/');
    $response->assertOk();
    $response->assertSee('id="gallery"', false);
    $response->assertSeeText('Aktive Mitglieder');
    $response->assertSeeText('Rezensionen');
    $response->assertSeeText('Community im echten Leben');
});
