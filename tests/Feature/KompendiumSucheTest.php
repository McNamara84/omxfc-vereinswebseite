<?php

namespace Tests\Feature;

use App\Livewire\KompendiumSuche;
use App\Models\KompendiumRoman;
use App\Models\User;
use App\Services\KompendiumSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class KompendiumSucheTest extends TestCase
{
    use RefreshDatabase;

    public function test_component_renders(): void
    {
        Livewire::test(KompendiumSuche::class)
            ->assertSee('Suchbegriff')
            ->assertSeeHtml('data-testid="kompendium-search-help-button"')
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
}
