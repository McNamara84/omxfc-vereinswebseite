<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Book;
use App\Models\Review;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('home page displays all sections and projects', function (): void {
    $response = $this->get('/');
    $response->assertOk()->assertSee('Willkommen beim Offiziellen MADDRAX Fanclub e. V.!')->assertSee('<title>Startseite – Offizieller MADDRAX Fanclub e. V.</title>', false)->assertSee('0 Community-Rezensionen zu MADDRAX-Romanen')->assertSee('Wer wir sind')->assertSee('Wir Maddrax-Fans sind eine muntere Gruppe')->assertSee('Was wir machen')->assertSee('Wir treffen uns in unterschiedlichen Konstellationen mal online')->assertSee('Aktuelle Projekte')->assertSee('Maddraxikon')->assertSee('EARDRAX')->assertSee('MAPDRAX')->assertSee('Fantreffen 2026')->assertSee('Aktuelle Veranstaltung')->assertSee('Vorteile einer Mitgliedschaft')->assertSee('Kostenlose Teilnahme an den jährlichen Fantreffen')->assertSee('Letzte Rezensionen')->assertSeeLivewire('home-reviews')->assertSee('Lesetipps und Eindrücke zu den Romanen unserer Lieblingsserie.', false)->assertSee('aktive Mitglieder');
});

test('home page displays member and review metrics', function (): void {
    $team = Team::factory()->create(['name' => 'Mitglieder']);
    $members = User::factory()->count(3)->create();
    $team->users()->attach($members->pluck('id'), ['role' => Role::Mitglied->value]);
    $book = Book::factory()->create();
    Review::factory()->count(4)->create(['team_id' => $team->id, 'user_id' => $members->first()->id, 'book_id' => $book->id]);
    Team::clearMembersTeamCache();
    Cache::forever(Team::MEMBERS_TEAM_ID_CACHE_KEY, $team->id);
    $response = $this->get('/');
    $response->assertOk()->assertSeeTextInOrder(['Aktive Mitglieder', '3', 'aktive Mitglieder'])->assertSeeTextInOrder(['Rezensionen', '4', 'Rezensionen'])->assertSee('aria-describedby="stat-members-description"', false)->assertSee('id="stat-members-description"', false)->assertSee('aria-describedby="stat-reviews-description"', false)->assertSee('id="stat-reviews-description"', false);
});

test('home page structured data exposes review count for maddrax books', function (): void {
    $team = Team::factory()->create(['name' => 'Mitglieder']);
    $members = User::factory()->count(2)->create();
    $team->users()->attach($members->pluck('id'), ['role' => Role::Mitglied->value]);
    $book = Book::factory()->create();
    Review::factory()->count(5)->create(['team_id' => $team->id, 'user_id' => $members->first()->id, 'book_id' => $book->id]);
    Team::clearMembersTeamCache();
    Cache::forever(Team::MEMBERS_TEAM_ID_CACHE_KEY, $team->id);
    $response = $this->get('/');
    $response->assertOk();
    preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $response->getContent(), $matches);
    expect($matches[1])->not->toBeEmpty();
    $structuredData = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
    $seriesEntry = collect($structuredData['@graph'])->firstWhere('@type', 'CreativeWorkSeries');
    expect($seriesEntry)->not->toBeNull()
        ->and($seriesEntry['reviewCount'])->toBe(5)
        ->and($seriesEntry['name'])->toContain('MADDRAX')
        ->and(strtolower($seriesEntry['about']))->toContain('rezensionen');
});

test('home page excludes soft deleted reviews from metrics', function (): void {
    $team = Team::factory()->create(['name' => 'Mitglieder']);
    $members = User::factory()->count(2)->create();
    $team->users()->attach($members->pluck('id'), ['role' => Role::Mitglied->value]);
    $book = Book::factory()->create();
    Review::factory()->count(2)->create(['team_id' => $team->id, 'user_id' => $members->first()->id, 'book_id' => $book->id]);
    $trashedReview = Review::factory()->create(['team_id' => $team->id, 'user_id' => $members->first()->id, 'book_id' => $book->id]);
    $trashedReview->delete();
    Team::clearMembersTeamCache();
    Cache::forever(Team::MEMBERS_TEAM_ID_CACHE_KEY, $team->id);
    $response = $this->get('/');
    $response->assertOk()->assertSeeTextInOrder(['Rezensionen', '2', 'Rezensionen'])->assertDontSee('3 Rezensionen');
});

test('home page contains structured data', function (): void {
    $response = $this->get('/');
    $response->assertOk()->assertSee('@context', false)->assertSee('SearchAction', false);
});

test('latest reviews link points to membership for guests', function (): void {
    $response = $this->get('/');
    $response->assertOk()->assertSee('href="'.route('mitglied.werden').'"', false)->assertDontSee('href="'.route('reviews.index').'"', false);
});

test('latest reviews link points to reviews for authenticated members', function (): void {
    $user = User::factory()->create();
    $response = $this->actingAs($user)->get('/');
    $response->assertOk();
    $card = (new Crawler($response->getContent()))->filter('#latest-reviews-card');
    expect($card->filter('a[href="'.route('reviews.index').'"]'))->toHaveCount(1)
        ->and($card->filter('a[href="'.route('mitglied.werden').'"]'))->toHaveCount(0);
});

test('latest reviews rendered by livewire component', function (): void {
    $response = $this->get('/');
    $response->assertOk()->assertSeeLivewire('home-reviews');
});

test('latest reviews empty state is announced accessibly', function (): void {
    $response = $this->get('/');
    $response->assertOk()->assertSee('role="status"', false)->assertSee('aria-live="polite"', false);
});
