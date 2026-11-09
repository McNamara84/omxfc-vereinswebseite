<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('termine page shows calendar sections and single main heading', function (): void {
    $response = $this->withoutVite()->get('/termine');
    $response->assertOk();
    $response->assertSeeText('Termine');
    $response->assertSeeText('Was im Kalender landet');
    $response->assertSeeText('Vereinskalender');
    $response->assertSeeText('Kalender abonnieren');
    $response->assertSee('href="'.route('arbeitsgruppen').'"', false);
    $response->assertDontSee('href="'.route('arbeitsgruppen.index').'"', false);
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('h1'))->toHaveCount(1);
});
