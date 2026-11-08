<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

beforeEach(function (): void {
    Config::set('app.testing_minimal_layout', true);
});

test('ehrenmitglieder page shows single main heading and context panels', function (): void {
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
    expect($crawler->filter('h1'))->toHaveCount(1);
});
