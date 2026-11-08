<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('satzung page shows single main heading and orientation panel', function (): void {
    $response = $this->withoutVite()->get('/satzung');
    $response->assertOk();
    $response->assertSeeText('Satzung des Offiziellen MADDRAX Fanclub e.V.');
    $response->assertSeeText('Fassung vom 14. März 2026');
    $response->assertSeeText('Schnelle Orientierung');
    $response->assertSeeText('Wofür die Satzung wichtig ist');
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('h1'))->toHaveCount(1);
});
