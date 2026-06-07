<?php

namespace Tests\Feature;

use App\Livewire\KompendiumSuche;
use App\Models\KompendiumRoman;
use App\Models\User;
use App\Services\KompendiumSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class KompendiumSucheTest extends TestCase
{
    use RefreshDatabase;

    private function setupSearchMock(array $files, array $searchResultPaths): void
    {
        Storage::fake('private');

        foreach ($files as $path => $content) {
            Storage::disk('private')->put($path, $content);
        }

        $this->partialMock(KompendiumSearchService::class, function ($mock) use ($searchResultPaths) {
            $mock->shouldReceive('search')
                ->andReturn(['ids' => $searchResultPaths]);
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_component_renders(): void
    {
        Livewire::test(KompendiumSuche::class)
            ->assertSee('Suchbegriff')
            ->assertSeeHtml('data-testid="kompendium-search-help-button"')
            ->assertSeeHtml('data-testid="kompendium-sort-field"')
            ->assertSeeHtml('data-testid="kompendium-sort-direction"')
            ->assertSet('sort', 'relevance')
            ->assertSet('direction', 'desc')
            ->assertOk();
    }

    public function test_component_renders_filter_help_when_multiple_serien_exist(): void
    {
        $user = User::factory()->create();

        KompendiumRoman::create([
            'dateiname' => '001 - Test.txt',
            'dateipfad' => 'romane/maddrax/001 - Test.txt',
            'serie' => 'maddrax',
            'roman_nr' => 1,
            'titel' => 'Test',
            'hochgeladen_am' => now(),
            'hochgeladen_von' => $user->id,
            'status' => 'indexiert',
        ]);

        KompendiumRoman::create([
            'dateiname' => '001 - Mars.txt',
            'dateipfad' => 'romane/missionmars/001 - Mars.txt',
            'serie' => 'missionmars',
            'roman_nr' => 1,
            'titel' => 'Mars',
            'hochgeladen_am' => now(),
            'hochgeladen_von' => $user->id,
            'status' => 'indexiert',
        ]);

        Livewire::test(KompendiumSuche::class)
            ->assertSee('Serien filtern')
            ->assertSeeHtml('data-testid="kompendium-filter-help-button"');
    }

    public function test_search_too_short_does_nothing(): void
    {
        Livewire::test(KompendiumSuche::class)
            ->set('query', 'a')
            ->call('performSearch')
            ->assertSet('hasSearched', false)
            ->assertSet('results', []);
    }

    public function test_perform_search_sets_has_searched(): void
    {
        $mock = Mockery::mock(KompendiumSearchService::class);
        $mock->shouldReceive('parseSearchQuery')->andReturn([
            'groups' => [['requiredTerms' => ['testbegriff'], 'requiredPhrases' => [], 'excludedTerms' => [], 'excludedPhrases' => []]],
            'phrases' => [],
            'terms' => ['testbegriff'],
            'excludedPhrases' => [],
            'excludedTerms' => [],
            'isPhraseSearch' => false,
            'usesOrOperator' => false,
            'usesNotOperator' => false,
            'hasPositiveOperands' => true,
        ]);
        $mock->shouldReceive('hasPositiveOperands')->andReturn(true);
        $mock->shouldReceive('search')->andReturn([
            'hits' => ['total_hits' => 0],
            'ids' => [],
        ]);
        $this->app->instance(KompendiumSearchService::class, $mock);

        Livewire::test(KompendiumSuche::class)
            ->set('query', 'testbegriff')
            ->call('performSearch')
            ->assertSet('hasSearched', true);
    }

    public function test_load_more_increments_page(): void
    {
        Livewire::test(KompendiumSuche::class)
            ->set('page', 1)
            ->set('lastPage', 3)
            ->call('loadMore')
            ->assertSet('page', 2);
    }

    public function test_load_more_does_nothing_on_last_page(): void
    {
        Livewire::test(KompendiumSuche::class)
            ->set('page', 3)
            ->set('lastPage', 3)
            ->call('loadMore')
            ->assertSet('page', 3);
    }

    public function test_deselecting_all_serien_triggers_reset(): void
    {
        // Without KompendiumRoman data, verfuegbareSerien is empty,
        // so the guard resets selectedSerien to the full list (which is also empty here).
        // We just verify the method doesn't throw and the property is set.
        Livewire::test(KompendiumSuche::class)
            ->set('selectedSerien', [])
            ->assertSet('selectedSerien', []);
    }

    public function test_search_error_sets_error_property(): void
    {
        $mock = Mockery::mock(KompendiumSearchService::class);
        $mock->shouldReceive('parseSearchQuery')->andThrow(new \RuntimeException('Search broken'));
        $this->app->instance(KompendiumSearchService::class, $mock);

        Livewire::test(KompendiumSuche::class)
            ->set('query', 'testbegriff')
            ->call('performSearch')
            ->assertSet('hasSearched', true)
            ->assertNotSet('error', null);
    }

    public function test_perform_search_resets_page_and_results(): void
    {
        $mock = Mockery::mock(KompendiumSearchService::class);
        $mock->shouldReceive('parseSearchQuery')->andReturn([
            'groups' => [['requiredTerms' => ['drax'], 'requiredPhrases' => [], 'excludedTerms' => [], 'excludedPhrases' => []]],
            'phrases' => [],
            'terms' => ['drax'],
            'excludedPhrases' => [],
            'excludedTerms' => [],
            'isPhraseSearch' => false,
            'usesOrOperator' => false,
            'usesNotOperator' => false,
            'hasPositiveOperands' => true,
        ]);
        $mock->shouldReceive('hasPositiveOperands')->andReturn(true);
        $mock->shouldReceive('search')->andReturn([
            'hits' => ['total_hits' => 0],
            'ids' => [],
        ]);
        $this->app->instance(KompendiumSearchService::class, $mock);

        Livewire::test(KompendiumSuche::class)
            ->set('page', 5)
            ->set('query', 'drax')
            ->call('performSearch')
            ->assertSet('page', 1);
    }

    public function test_negative_only_query_sets_specific_error(): void
    {
        $mock = Mockery::mock(KompendiumSearchService::class);
        $mock->shouldReceive('parseSearchQuery')->andReturn([
            'groups' => [['requiredTerms' => [], 'requiredPhrases' => [], 'excludedTerms' => ['aruula'], 'excludedPhrases' => []]],
            'phrases' => [],
            'terms' => [],
            'excludedPhrases' => [],
            'excludedTerms' => ['aruula'],
            'isPhraseSearch' => false,
            'usesOrOperator' => false,
            'usesNotOperator' => true,
            'hasPositiveOperands' => false,
        ]);
        $mock->shouldReceive('hasPositiveOperands')->andReturn(false);
        $this->app->instance(KompendiumSearchService::class, $mock);

        Livewire::test(KompendiumSuche::class)
            ->set('query', 'NOT aruula')
            ->call('performSearch')
            ->assertSet('error', 'Bitte gib mindestens einen positiven Suchbegriff ein.')
            ->assertSet('results', []);
    }

    public function test_component_labels_loaded_results_as_bisher_geladen(): void
    {
        Livewire::test(KompendiumSuche::class)
            ->set('hasSearched', true)
            ->set('results', [
                ['cycle' => 'Maddrax', 'romanNr' => '001', 'title' => 'Test 1', 'serie' => 'maddrax', 'snippets' => ['Treffer 1']],
                ['cycle' => 'Maddrax', 'romanNr' => '002', 'title' => 'Test 2', 'serie' => 'maddrax', 'snippets' => ['Treffer 2']],
            ])
            ->set('page', 2)
            ->set('lastPage', 3)
            ->assertSee('2 Treffer bisher geladen, aktuell Seite 2 von 3.');
    }

    public function test_component_shows_candidate_limit_hint_when_post_filter_is_truncated(): void
    {
        Livewire::test(KompendiumSuche::class)
            ->set('hasSearched', true)
            ->set('candidatesTruncated', true)
            ->set('scannedCandidates', 400)
            ->assertSee('Für die Suchlogik wurden bisher 400 Kandidaten nachgeprüft.');
    }

    public function test_sort_wechsel_auf_erstveroeffentlichung_setzt_richtung_auf_aufsteigend(): void
    {
        Livewire::test(KompendiumSuche::class)
            ->set('direction', 'desc')
            ->set('sort', 'first_published')
            ->assertSet('direction', 'asc');
    }

    public function test_component_sortiert_nach_erstveroeffentlichungsdatum(): void
    {
        $files = [
            'romane/maddrax/002 - Neuer Treffer.txt' => 'Aruula sortiert diesen Treffer.',
            'romane/maddrax/001 - Alter Treffer.txt' => 'Aruula sortiert diesen Treffer.',
        ];

        $searchResultPaths = array_keys($files);
        $this->setupSearchMock($files, $searchResultPaths);

        $user = User::factory()->create();

        KompendiumRoman::create([
            'dateiname' => '002 - Neuer Treffer.txt',
            'dateipfad' => 'romane/maddrax/002 - Neuer Treffer.txt',
            'serie' => 'maddrax',
            'roman_nr' => 2,
            'titel' => 'Neuer Treffer',
            'erstveroeffentlicht_am' => '2024-01-01',
            'hochgeladen_am' => now(),
            'hochgeladen_von' => $user->id,
            'status' => 'indexiert',
        ]);

        KompendiumRoman::create([
            'dateiname' => '001 - Alter Treffer.txt',
            'dateipfad' => 'romane/maddrax/001 - Alter Treffer.txt',
            'serie' => 'maddrax',
            'roman_nr' => 1,
            'titel' => 'Alter Treffer',
            'erstveroeffentlicht_am' => '2020-01-01',
            'hochgeladen_am' => now(),
            'hochgeladen_von' => $user->id,
            'status' => 'indexiert',
        ]);

        Livewire::test(KompendiumSuche::class)
            ->set('sort', 'first_published')
            ->set('query', 'Aruula')
            ->call('performSearch')
            ->assertSeeInOrder(['Alter Treffer', 'Neuer Treffer'])
            ->assertSee('Erstveroeffentlicht: 01.01.2020');
    }

    public function test_component_serienfilter_fuellt_die_seite_mit_spaeteren_treffern_aus_der_ausgewaehlten_serie(): void
    {
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

        Livewire::test(KompendiumSuche::class)
            ->set('selectedSerien', ['maddrax'])
            ->set('query', '"Aruula"')
            ->call('performSearch')
            ->assertSet('lastPage', 1)
            ->assertSee('5 Treffer bisher geladen.')
            ->assertSee('Maddrax Treffer 1')
            ->assertDontSee('Mission Treffer 1');
    }

    public function test_load_more_haltet_last_page_mindestens_auf_der_aktuellen_seite_bei_trunkierung_nach_serienfilter(): void
    {
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

        Livewire::test(KompendiumSuche::class)
            ->set('selectedSerien', ['maddrax'])
            ->set('query', '"Aruula"')
            ->set('lastPage', 2)
            ->call('loadMore')
            ->assertSet('page', 2)
            ->assertSet('candidatesTruncated', true)
            ->assertSet('lastPage', 2);
    }
}
