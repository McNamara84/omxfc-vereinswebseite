<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('changelog page shows context panels and single main heading', function (): void {
    $response = $this->withoutVite()->get('/changelog');
    $response->assertOk();
    $response->assertSeeText('Changelog');
    $response->assertSeeText('Änderungen an der Vereinswebseite');
    $response->assertSeeText('Wie du den Changelog liest');
    $response->assertSeeText('Wofür das nützlich ist');
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('h1'))->toHaveCount(1);
});
