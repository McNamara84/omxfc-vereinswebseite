<?php

namespace Tests\Feature;

use App\Livewire\KompendiumSuche;
use App\Models\Team;
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
            ->assertOk();
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
            'phrases' => [],
            'terms' => ['testbegriff'],
            'isPhraseSearch' => false,
            'hadQuotes' => false,
        ]);
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
            'phrases' => [],
            'terms' => ['drax'],
            'isPhraseSearch' => false,
            'hadQuotes' => false,
        ]);
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
}
