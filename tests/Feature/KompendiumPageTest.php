<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

pest()->extend(TestCase::class)->use(KompendiumPageTestFixtures::class);

test('kompendium page shows context panels for members', function (): void {
    $this->actingMember();
    $response = $this->withoutVite()->get('/kompendium');
    $html = $response->getContent();
    $response->assertOk();
    $response->assertSeeText('Maddrax-Kompendium');
    $response->assertSeeText('Indexierte Serien');
    $response->assertSeeText('Aktueller Stand');
    $response->assertSee('data-testid="kompendium-primary-access"', false);
    $response->assertSee('data-testid="kompendium-access-help-button"', false);
    $response->assertSee('data-testid="kompendium-series-help-button"', false);
    $primaryAccessPosition = strpos($html, 'data-testid="kompendium-primary-access"');
    $indexedSeriesPosition = strpos($html, 'data-testid="kompendium-series-help-button"');
    expect($primaryAccessPosition)->toBeInt()
        ->and($indexedSeriesPosition)->toBeInt()
        ->and($primaryAccessPosition)->toBeLessThan($indexedSeriesPosition);
});

test('unlocked kompendium page places search before secondary panels', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    $response = $this->withoutVite()->get('/kompendium');
    $html = $response->getContent();
    $response->assertOk();
    $response->assertSeeLivewire('kompendium-suche');
    $response->assertSee('data-testid="kompendium-primary-search"', false);
    $response->assertSee('data-testid="kompendium-search-help-button"', false);
    $response->assertSee('data-testid="kompendium-series-help-button"', false);
    $primarySearchPosition = strpos($html, 'data-testid="kompendium-primary-search"');
    $indexedSeriesPosition = strpos($html, 'data-testid="kompendium-series-help-button"');
    expect($primarySearchPosition)->toBeInt()
        ->and($indexedSeriesPosition)->toBeInt()
        ->and($primarySearchPosition)->toBeLessThan($indexedSeriesPosition);
});
