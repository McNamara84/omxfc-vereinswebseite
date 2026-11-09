<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MaddraxikonAccountLinkStatus;
use App\Livewire\RezensionShow;
use Livewire\Livewire;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(MaddraxikonReviewRatingUiTestFixtures::class);

beforeEach(function (): void {
    config(['maddraxikon.features.ratings_enabled' => true, 'maddraxikon.ratings.stale_after_minutes' => 60, 'maddraxikon.base_url' => 'https://de.maddraxikon.com', 'maddraxikon.wiki_key' => 'test-wiki', 'maddraxikon.consent_version' => 'ratings-consent-v2']);
});

test('review renders the personal rating as five accessible green comets with fire tails', function (): void {
    [$review, $book] = $this->createReviewWithRating(4);
    $component = Livewire::actingAs($review->user)->test(RezensionShow::class, ['book' => $book])->assertSee('Bewertung im Maddraxikon')->assertSee('4 von 5 Kometen')->assertSeeHtml('data-rating="4"')->assertSeeHtml('aria-label="4 von 5 Kometen – Romanseite im Maddraxikon öffnen"')->assertSeeHtml('href="https://de.maddraxikon.com/index.php?title=MX_100_%E2%80%93_Testroman"')->assertSeeHtml('target="_blank"')->assertSeeHtml('rel="noopener noreferrer"');
    $html = $component->html();
    expect(substr_count($html, 'data-comet-position='))->toBe(5)
        ->and(substr_count($html, 'data-comet-filled="true"'))->toBe(4)
        ->and(substr_count($html, 'data-comet-filled="false"'))->toBe(1)
        ->and($html)->toContain('#22c55e')
        ->and(substr_count($html, 'class="maddraxikon-comet__tail"'))->toBe(4)
        ->and(substr_count($html, 'class="maddraxikon-comet__nucleus"'))->toBe(4)
        ->and($html)->toContain('#dc2626')
        ->toContain('#f97316')
        ->toContain('#fbbf24');
});

test('stale or disconnected or disabled ratings are not rendered', function (): void {
    [$review, $book, $link, $snapshot] = $this->createReviewWithRating(5);
    $snapshot->update(['synced_at' => now()->subMinutes(61)]);
    Livewire::actingAs($review->user)->test(RezensionShow::class, ['book' => $book])->assertDontSee('Bewertung im Maddraxikon');
    $snapshot->update(['synced_at' => now()]);
    $link->update(['status' => MaddraxikonAccountLinkStatus::Disconnected, 'disconnected_at' => now()]);
    Livewire::actingAs($review->user)->test(RezensionShow::class, ['book' => $book])->assertDontSee('Bewertung im Maddraxikon');
    $link->update(['status' => MaddraxikonAccountLinkStatus::Active, 'disconnected_at' => null]);
    config(['maddraxikon.features.ratings_enabled' => false]);
    Livewire::actingAs($review->user)->test(RezensionShow::class, ['book' => $book])->assertDontSee('Bewertung im Maddraxikon');
});

test('snapshot for another identity or page is never rendered', function (): void {
    [$review, $book, $link, $snapshot] = $this->createReviewWithRating(3);
    $snapshot->update(['wiki_user_id' => $link->wiki_user_id + 1]);
    Livewire::actingAs($review->user)->test(RezensionShow::class, ['book' => $book])->assertDontSee('Bewertung im Maddraxikon');
    $snapshot->update(['wiki_user_id' => $link->wiki_user_id, 'maddraxikon_page_id' => $book->maddraxikon_page_id + 1]);
    Livewire::actingAs($review->user)->test(RezensionShow::class, ['book' => $book])->assertDontSee('Bewertung im Maddraxikon');
});

test('wrong wiki or outdated consent is never rendered', function (): void {
    [$review, $book, $link] = $this->createReviewWithRating(3);
    $link->update(['wiki_key' => 'another-wiki']);
    Livewire::actingAs($review->user)->test(RezensionShow::class, ['book' => $book])->assertDontSee('Bewertung im Maddraxikon');
    $link->update(['wiki_key' => 'test-wiki', 'consent_version' => 'legacy-consent']);
    Livewire::actingAs($review->user)->test(RezensionShow::class, ['book' => $book])->assertDontSee('Bewertung im Maddraxikon');
});
