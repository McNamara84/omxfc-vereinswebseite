<?php

namespace Tests\Feature;

use App\Enums\MaddraxikonAccountLinkStatus;
use App\Livewire\RezensionShow;
use App\Models\Book;
use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonReviewRating;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class MaddraxikonReviewRatingUiTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'maddraxikon.features.ratings_enabled' => true,
            'maddraxikon.ratings.stale_after_minutes' => 60,
            'maddraxikon.base_url' => 'https://de.maddraxikon.com',
            'maddraxikon.wiki_key' => 'test-wiki',
            'maddraxikon.consent_version' => 'ratings-consent-v2',
        ]);
    }

    public function test_review_renders_the_personal_rating_as_five_accessible_green_comets_with_fire_tails(): void
    {
        [$review, $book] = $this->createReviewWithRating(4);

        $component = Livewire::actingAs($review->user)
            ->test(RezensionShow::class, ['book' => $book])
            ->assertSee('Bewertung im Maddraxikon')
            ->assertSee('4 von 5 Kometen')
            ->assertSeeHtml('data-rating="4"')
            ->assertSeeHtml('aria-label="4 von 5 Kometen – Romanseite im Maddraxikon öffnen"')
            ->assertSeeHtml('href="https://de.maddraxikon.com/index.php?title=MX_100_%E2%80%93_Testroman"')
            ->assertSeeHtml('target="_blank"')
            ->assertSeeHtml('rel="noopener noreferrer"');

        $html = $component->html();
        $this->assertSame(5, substr_count($html, 'data-comet-position='));
        $this->assertSame(4, substr_count($html, 'data-comet-filled="true"'));
        $this->assertSame(1, substr_count($html, 'data-comet-filled="false"'));
        $this->assertStringContainsString('#22c55e', $html);
        $this->assertSame(4, substr_count($html, 'class="maddraxikon-comet__tail"'));
        $this->assertSame(4, substr_count($html, 'class="maddraxikon-comet__nucleus"'));
        $this->assertStringContainsString('#dc2626', $html);
        $this->assertStringContainsString('#f97316', $html);
        $this->assertStringContainsString('#fbbf24', $html);
    }

    public function test_stale_or_disconnected_or_disabled_ratings_are_not_rendered(): void
    {
        [$review, $book, $link, $snapshot] = $this->createReviewWithRating(5);
        $snapshot->update(['synced_at' => now()->subMinutes(61)]);

        Livewire::actingAs($review->user)
            ->test(RezensionShow::class, ['book' => $book])
            ->assertDontSee('Bewertung im Maddraxikon');

        $snapshot->update(['synced_at' => now()]);
        $link->update([
            'status' => MaddraxikonAccountLinkStatus::Disconnected,
            'disconnected_at' => now(),
        ]);

        Livewire::actingAs($review->user)
            ->test(RezensionShow::class, ['book' => $book])
            ->assertDontSee('Bewertung im Maddraxikon');

        $link->update([
            'status' => MaddraxikonAccountLinkStatus::Active,
            'disconnected_at' => null,
        ]);
        config(['maddraxikon.features.ratings_enabled' => false]);

        Livewire::actingAs($review->user)
            ->test(RezensionShow::class, ['book' => $book])
            ->assertDontSee('Bewertung im Maddraxikon');
    }

    public function test_snapshot_for_another_identity_or_page_is_never_rendered(): void
    {
        [$review, $book, $link, $snapshot] = $this->createReviewWithRating(3);
        $snapshot->update(['wiki_user_id' => $link->wiki_user_id + 1]);

        Livewire::actingAs($review->user)
            ->test(RezensionShow::class, ['book' => $book])
            ->assertDontSee('Bewertung im Maddraxikon');

        $snapshot->update([
            'wiki_user_id' => $link->wiki_user_id,
            'maddraxikon_page_id' => $book->maddraxikon_page_id + 1,
        ]);

        Livewire::actingAs($review->user)
            ->test(RezensionShow::class, ['book' => $book])
            ->assertDontSee('Bewertung im Maddraxikon');
    }

    public function test_wrong_wiki_or_outdated_consent_is_never_rendered(): void
    {
        [$review, $book, $link] = $this->createReviewWithRating(3);
        $link->update(['wiki_key' => 'another-wiki']);

        Livewire::actingAs($review->user)
            ->test(RezensionShow::class, ['book' => $book])
            ->assertDontSee('Bewertung im Maddraxikon');

        $link->update([
            'wiki_key' => 'test-wiki',
            'consent_version' => 'legacy-consent',
        ]);

        Livewire::actingAs($review->user)
            ->test(RezensionShow::class, ['book' => $book])
            ->assertDontSee('Bewertung im Maddraxikon');
    }

    /**
     * @return array{Review, Book, MaddraxikonAccountLink, MaddraxikonReviewRating}
     */
    private function createReviewWithRating(int $rating): array
    {
        $user = $this->actingMember();
        $book = Book::factory()->mapped(100, 'MX 100 – Testroman')->create();
        $review = Review::factory()->create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'book_id' => $book->id,
            'title' => 'Rezension mit Kometen',
        ]);
        $link = MaddraxikonAccountLink::factory()->for($user)->create([
            'wiki_user_id' => 42,
        ]);
        $snapshot = MaddraxikonReviewRating::query()->create([
            'review_id' => $review->id,
            'book_id' => $book->id,
            'user_id' => $user->id,
            'account_link_id' => $link->id,
            'maddraxikon_page_id' => $book->maddraxikon_page_id,
            'wiki_user_id' => $link->wiki_user_id,
            'rating' => $rating,
            'source_voted_at' => now()->subHour(),
            'synced_at' => now(),
        ]);

        return [$review, $book, $link, $snapshot];
    }
}
