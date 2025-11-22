<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Book;
use App\Models\Review;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_displays_all_sections_and_projects(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Willkommen beim Offiziellen MADDRAX Fanclub e. V.!')
            ->assertSee('<title>Startseite – Offizieller MADDRAX Fanclub e. V.</title>', false)
            ->assertSee('0 Community-Rezensionen zu MADDRAX-Romanen')
            ->assertSee('Wer wir sind')
            ->assertSee('Wir Maddrax-Fans sind eine muntere Gruppe')
            ->assertSee('Was wir machen')
            ->assertSee('Wir treffen uns in unterschiedlichen Konstellationen mal online')
            ->assertSee('Aktuelle Projekte')
            ->assertSee('Maddraxikon')
            ->assertSee('EARDRAX')
            ->assertSee('MAPDRAX')
            ->assertSee('Fantreffen 2026')
            ->assertSee('Vorteile einer Mitgliedschaft')
            ->assertSee('Kostenlose Teilnahme an den jährlichen Fantreffen')
            ->assertSee('Letzte Rezensionen')
            ->assertSee('Lädt Community-Highlights', false)
            ->assertSee('aktive Mitglieder');
    }

    public function test_home_page_displays_member_and_review_metrics(): void
    {
        $team = Team::factory()->create(['name' => 'Mitglieder']);
        $members = User::factory()->count(3)->create();

        $team->users()->attach(
            $members->pluck('id'),
            ['role' => Role::Mitglied->value]
        );

        $book = Book::factory()->create();

        Review::factory()->count(4)->create([
            'team_id' => $team->id,
            'user_id' => $members->first()->id,
            'book_id' => $book->id,
        ]);

        Team::clearMembersTeamCache();
        Cache::forever(Team::MEMBERS_TEAM_CACHE_KEY, $team);
        Cache::forever(Team::MEMBERS_TEAM_ID_CACHE_KEY, $team->id);

        $response = $this->get('/');

        $response->assertOk()
            ->assertSeeTextInOrder(['Aktive Mitglieder', '3', 'aktive Mitglieder'])
            ->assertSeeTextInOrder(['Rezensionen', '4', 'Rezensionen'])
            ->assertSee('aria-describedby="stat-members-description"', false)
            ->assertSee('id="stat-members-description"', false)
            ->assertSee('aria-describedby="stat-reviews-description"', false)
            ->assertSee('id="stat-reviews-description"', false);
    }

    public function test_home_page_structured_data_exposes_review_count_for_maddrax_books(): void
    {
        $team = Team::factory()->create(['name' => 'Mitglieder']);
        $members = User::factory()->count(2)->create();

        $team->users()->attach(
            $members->pluck('id'),
            ['role' => Role::Mitglied->value]
        );

        $book = Book::factory()->create();

        Review::factory()->count(5)->create([
            'team_id' => $team->id,
            'user_id' => $members->first()->id,
            'book_id' => $book->id,
        ]);

        Team::clearMembersTeamCache();
        Cache::forever(Team::MEMBERS_TEAM_CACHE_KEY, $team);
        Cache::forever(Team::MEMBERS_TEAM_ID_CACHE_KEY, $team->id);

        $response = $this->get('/');

        $response->assertOk();

        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $response->getContent(), $matches);

        $this->assertNotEmpty($matches[1], 'Structured data block should be present');

        $structuredData = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);

        $seriesEntry = collect($structuredData['@graph'])
            ->firstWhere('@type', 'CreativeWorkSeries');

        $this->assertNotNull($seriesEntry);
        $this->assertSame(5, $seriesEntry['reviewCount']);
        $this->assertStringContainsString('MADDRAX', $seriesEntry['name']);
        $this->assertStringContainsString('rezensionen', strtolower($seriesEntry['about']));
    }

    public function test_home_page_excludes_soft_deleted_reviews_from_metrics(): void
    {
        $team = Team::factory()->create(['name' => 'Mitglieder']);
        $members = User::factory()->count(2)->create();

        $team->users()->attach(
            $members->pluck('id'),
            ['role' => Role::Mitglied->value]
        );

        $book = Book::factory()->create();

        Review::factory()->count(2)->create([
            'team_id' => $team->id,
            'user_id' => $members->first()->id,
            'book_id' => $book->id,
        ]);

        $trashedReview = Review::factory()->create([
            'team_id' => $team->id,
            'user_id' => $members->first()->id,
            'book_id' => $book->id,
        ]);

        $trashedReview->delete();

        Team::clearMembersTeamCache();
        Cache::forever(Team::MEMBERS_TEAM_CACHE_KEY, $team);
        Cache::forever(Team::MEMBERS_TEAM_ID_CACHE_KEY, $team->id);

        $response = $this->get('/');

        $response->assertOk()
            ->assertSeeTextInOrder(['Rezensionen', '2', 'Rezensionen'])
            ->assertDontSee('3 Rezensionen');
    }

    public function test_home_page_contains_structured_data(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('@context', false)
            ->assertSee('SearchAction', false);
    }

    public function test_latest_reviews_link_points_to_membership_for_guests(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('href="' . route('mitglied.werden') . '"', false)
            ->assertDontSee('href="' . route('reviews.index') . '"', false);
    }

    public function test_latest_reviews_link_points_to_reviews_for_authenticated_members(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertOk()
            ->assertSee('href="' . route('reviews.index') . '"', false)
            ->assertDontSee('href="' . route('mitglied.werden') . '"', false);
    }

    public function test_latest_reviews_loading_state_updates_aria_busy_on_error(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('id="latest-reviews-loading"', false)
            ->assertSee('aria-busy="true"', false)
            ->assertSee("loading.setAttribute('aria-busy', 'false');", false)
            ->assertSee("errorMessage.setAttribute('role', 'status');", false)
            ->assertSee("document.addEventListener('DOMContentLoaded', () => {", false);
    }

    public function test_latest_reviews_empty_state_is_announced_accessibly(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('id="latest-reviews-empty"', false)
            ->assertSee('role="status"', false)
            ->assertSee('aria-live="polite"', false);
    }
}
