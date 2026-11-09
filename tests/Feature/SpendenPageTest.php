<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('spenden page shows support sections and single main heading', function (): void {
    $response = $this->withoutVite()->get('/spenden');
    $response->assertOk();
    $response->assertSeeText('Spenden');
    $response->assertSeeText('Was deine Spende ermöglicht');
    $response->assertSeeText('Direkt per PayPal');
    $response->assertSeeText('kassenwart@maddrax-fanclub.de');
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('h1'))->toHaveCount(1);
});
