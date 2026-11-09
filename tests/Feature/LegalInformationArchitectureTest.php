<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('impressum page shows context panels and single main heading', function (): void {
    $response = $this->withoutVite()->get('/impressum');
    $response->assertOk();
    $response->assertSeeText('Impressum');
    $response->assertSeeText('Angaben gemäß §5 TMG');
    $response->assertSeeText('Direkter Kontakt');
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('h1'))->toHaveCount(1);
});

test('datenschutz page shows context panels and single main heading', function (): void {
    $response = $this->withoutVite()->get('/datenschutz');
    $response->assertOk();
    $response->assertSeeText('Datenschutz');
    $response->assertSeeText('Datenschutzerklärung');
    $response->assertSeeText('Kontakt zum Verantwortlichen');
    $response->assertSeeText('Kurzüberblick');
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('h1'))->toHaveCount(1);
});
