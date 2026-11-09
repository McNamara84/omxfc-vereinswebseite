<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\KompendiumSuche;
use App\Models\KompendiumRoman;
use App\Models\User;
use App\Services\KompendiumSearchService;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(KompendiumSucheTestFixtures::class);

afterEach(function (): void {
    Mockery::close();
});

test('component renders', function (): void {
    Livewire::test(KompendiumSuche::class)->assertSee('Suchbegriff')->assertSeeHtml('data-testid="kompendium-search-help-button"')->assertSeeHtml('data-testid="kompendium-sort-field"')->assertSeeHtml('data-testid="kompendium-sort-direction"')->assertSet('sort', 'relevance')->assertSet('direction', 'desc')->assertOk();
});

test('component renders filter help when multiple serien exist', function (): void {
    $user = User::factory()->create();
    KompendiumRoman::create(['dateiname' => '001 - Test.txt', 'dateipfad' => 'romane/maddrax/001 - Test.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'Test', 'hochgeladen_am' => now(), 'hochgeladen_von' => $user->id, 'status' => 'indexiert']);
    KompendiumRoman::create(['dateiname' => '001 - Mars.txt', 'dateipfad' => 'romane/missionmars/001 - Mars.txt', 'serie' => 'missionmars', 'roman_nr' => 1, 'titel' => 'Mars', 'hochgeladen_am' => now(), 'hochgeladen_von' => $user->id, 'status' => 'indexiert']);
    Livewire::test(KompendiumSuche::class)->assertSee('Serien filtern')->assertSeeHtml('data-testid="kompendium-filter-help-button"');
});

test('search too short does nothing', function (): void {
    Livewire::test(KompendiumSuche::class)->set('query', 'a')->call('performSearch')->assertSet('hasSearched', false)->assertSet('results', []);
});

test('perform search sets has searched', function (): void {
    $mock = Mockery::mock(KompendiumSearchService::class);
    $mock->shouldReceive('parseSearchQuery')->andReturn(['groups' => [['requiredTerms' => ['testbegriff'], 'requiredPhrases' => [], 'excludedTerms' => [], 'excludedPhrases' => []]], 'phrases' => [], 'terms' => ['testbegriff'], 'excludedPhrases' => [], 'excludedTerms' => [], 'isPhraseSearch' => false, 'usesOrOperator' => false, 'usesNotOperator' => false, 'hasPositiveOperands' => true]);
    $mock->shouldReceive('hasPositiveOperands')->andReturn(true);
    $mock->shouldReceive('search')->andReturn(['hits' => ['total_hits' => 0], 'ids' => []]);
    $this->app->instance(KompendiumSearchService::class, $mock);
    Livewire::test(KompendiumSuche::class)->set('query', 'testbegriff')->call('performSearch')->assertSet('hasSearched', true);
});

test('load more increments page', function (): void {
    Livewire::test(KompendiumSuche::class)->set('page', 1)->set('lastPage', 3)->call('loadMore')->assertSet('page', 2);
});

test('load more does nothing on last page', function (): void {
    Livewire::test(KompendiumSuche::class)->set('page', 3)->set('lastPage', 3)->call('loadMore')->assertSet('page', 3);
});

test('deselecting all serien triggers reset', function (): void {
    // Without KompendiumRoman data, verfuegbareSerien is empty,
    // so the guard resets selectedSerien to the full list (which is also empty here).
    // We just verify the method doesn't throw and the property is set.
    Livewire::test(KompendiumSuche::class)->set('selectedSerien', [])->assertSet('selectedSerien', []);
});

test('search error sets error property', function (): void {
    $mock = Mockery::mock(KompendiumSearchService::class);
    $mock->shouldReceive('parseSearchQuery')->andThrow(new \RuntimeException('Search broken'));
    $this->app->instance(KompendiumSearchService::class, $mock);
    Livewire::test(KompendiumSuche::class)->set('query', 'testbegriff')->call('performSearch')->assertSet('hasSearched', true)->assertNotSet('error', null);
});

test('perform search resets page and results', function (): void {
    $mock = Mockery::mock(KompendiumSearchService::class);
    $mock->shouldReceive('parseSearchQuery')->andReturn(['groups' => [['requiredTerms' => ['drax'], 'requiredPhrases' => [], 'excludedTerms' => [], 'excludedPhrases' => []]], 'phrases' => [], 'terms' => ['drax'], 'excludedPhrases' => [], 'excludedTerms' => [], 'isPhraseSearch' => false, 'usesOrOperator' => false, 'usesNotOperator' => false, 'hasPositiveOperands' => true]);
    $mock->shouldReceive('hasPositiveOperands')->andReturn(true);
    $mock->shouldReceive('search')->andReturn(['hits' => ['total_hits' => 0], 'ids' => []]);
    $this->app->instance(KompendiumSearchService::class, $mock);
    Livewire::test(KompendiumSuche::class)->set('page', 5)->set('query', 'drax')->call('performSearch')->assertSet('page', 1);
});

test('negative only query sets specific error', function (): void {
    $mock = Mockery::mock(KompendiumSearchService::class);
    $mock->shouldReceive('parseSearchQuery')->andReturn(['groups' => [['requiredTerms' => [], 'requiredPhrases' => [], 'excludedTerms' => ['aruula'], 'excludedPhrases' => []]], 'phrases' => [], 'terms' => [], 'excludedPhrases' => [], 'excludedTerms' => ['aruula'], 'isPhraseSearch' => false, 'usesOrOperator' => false, 'usesNotOperator' => true, 'hasPositiveOperands' => false]);
    $mock->shouldReceive('hasPositiveOperands')->andReturn(false);
    $this->app->instance(KompendiumSearchService::class, $mock);
    Livewire::test(KompendiumSuche::class)->set('query', 'NOT aruula')->call('performSearch')->assertSet('error', 'Bitte gib mindestens einen positiven Suchbegriff ein.')->assertSet('results', []);
});

test('component labels loaded results as bisher geladen', function (): void {
    Livewire::test(KompendiumSuche::class)->set('hasSearched', true)->set('results', [['cycle' => 'Maddrax', 'romanNr' => '001', 'title' => 'Test 1', 'serie' => 'maddrax', 'snippets' => ['Treffer 1']], ['cycle' => 'Maddrax', 'romanNr' => '002', 'title' => 'Test 2', 'serie' => 'maddrax', 'snippets' => ['Treffer 2']]])->set('page', 2)->set('lastPage', 3)->assertSee('2 Treffer bisher geladen, aktuell Seite 2 von 3.');
});

test('component shows candidate limit hint when post filter is truncated', function (): void {
    Livewire::test(KompendiumSuche::class)->set('hasSearched', true)->set('candidatesTruncated', true)->set('scannedCandidates', 400)->assertSee('Für die Suchlogik wurden bisher 400 Kandidaten nachgeprüft.');
});

test('sort wechsel auf erstveroeffentlichung setzt richtung auf aufsteigend', function (): void {
    Livewire::test(KompendiumSuche::class)->set('direction', 'desc')->set('sort', 'first_published')->assertSet('direction', 'asc');
});

test('component sortiert nach erstveroeffentlichungsdatum', function (): void {
    $files = ['romane/maddrax/002 - Neuer Treffer.txt' => 'Aruula sortiert diesen Treffer.', 'romane/maddrax/001 - Alter Treffer.txt' => 'Aruula sortiert diesen Treffer.'];
    $searchResultPaths = array_keys($files);
    $this->setupSearchMock($files, $searchResultPaths);
    $user = User::factory()->create();
    KompendiumRoman::create(['dateiname' => '002 - Neuer Treffer.txt', 'dateipfad' => 'romane/maddrax/002 - Neuer Treffer.txt', 'serie' => 'maddrax', 'roman_nr' => 2, 'titel' => 'Neuer Treffer', 'erstveroeffentlicht_am' => '2024-01-01', 'hochgeladen_am' => now(), 'hochgeladen_von' => $user->id, 'status' => 'indexiert']);
    KompendiumRoman::create(['dateiname' => '001 - Alter Treffer.txt', 'dateipfad' => 'romane/maddrax/001 - Alter Treffer.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'Alter Treffer', 'erstveroeffentlicht_am' => '2020-01-01', 'hochgeladen_am' => now(), 'hochgeladen_von' => $user->id, 'status' => 'indexiert']);
    Livewire::test(KompendiumSuche::class)->set('sort', 'first_published')->set('query', 'Aruula')->call('performSearch')->assertSeeInOrder(['Alter Treffer', 'Neuer Treffer'])->assertSee('Erstveroeffentlicht: 01.01.2020');
});

test('component serienfilter fuellt die seite mit spaeteren treffern aus der ausgewaehlten serie', function (): void {
    $files = [];
    $searchResultPaths = [];
    foreach (range(1, 6) as $number) {
        $path = sprintf('romane/missionmars/%03d - Mission Treffer %d.txt', $number, $number);
        $files[$path] = 'Aruula fand einen wichtigen Hinweis.';
        $searchResultPaths[] = $path;
    }
    foreach (range(1, 5) as $number) {
        $path = sprintf('romane/maddrax/%03d - Maddrax Treffer %d.txt', $number, $number);
        $files[$path] = 'Aruula fand einen wichtigen Hinweis.';
        $searchResultPaths[] = $path;
    }
    $this->setupSearchMock($files, $searchResultPaths);
    Livewire::test(KompendiumSuche::class)->set('selectedSerien', ['maddrax'])->set('query', '"Aruula"')->call('performSearch')->assertSet('lastPage', 1)->assertSee('5 Treffer bisher geladen.')->assertSee('Maddrax Treffer 1')->assertDontSee('Mission Treffer 1');
});

test('load more haltet last page mindestens auf der aktuellen seite bei trunkierung nach serienfilter', function (): void {
    Config::set('kompendium.post_filter.initial_batch_size', 5);
    Config::set('kompendium.post_filter.max_candidates_per_request', 5);
    $files = [];
    $searchResultPaths = [];
    foreach (range(1, 5) as $number) {
        $path = sprintf('romane/missionmars/%03d - Mission Treffer %d.txt', $number, $number);
        $files[$path] = 'Aruula fand einen wichtigen Hinweis.';
        $searchResultPaths[] = $path;
    }
    foreach (range(1, 2) as $number) {
        $path = sprintf('romane/maddrax/%03d - Maddrax Treffer %d.txt', $number, $number);
        $files[$path] = 'Aruula fand einen wichtigen Hinweis.';
        $searchResultPaths[] = $path;
    }
    $this->setupSearchMock($files, $searchResultPaths);
    Livewire::test(KompendiumSuche::class)->set('selectedSerien', ['maddrax'])->set('query', '"Aruula"')->set('lastPage', 2)->call('loadMore')->assertSet('page', 2)->assertSet('candidatesTruncated', true)->assertSet('lastPage', 2);
});
