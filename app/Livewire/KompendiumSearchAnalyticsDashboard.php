<?php

namespace App\Livewire;

use App\Services\KompendiumSearchAnalyticsService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Kompendium-Suchstatistik')]
class KompendiumSearchAnalyticsDashboard extends Component
{
    use WithPagination;

    public string $from = '';

    public string $to = '';

    public string $userId = '';

    public string $source = '';

    public string $term = '';

    public bool $onlyZeroResults = false;

    public bool $includeAdminSearches = false;

    public function mount(): void
    {
        $this->from = now()->subDays(30)->toDateString();
        $this->to = now()->toDateString();
    }

    public function updatedFrom(): void
    {
        $this->resetPage();
    }

    public function updatedTo(): void
    {
        $this->resetPage();
    }

    public function updatedUserId(): void
    {
        $this->resetPage();
    }

    public function updatedSource(): void
    {
        $this->resetPage();
    }

    public function updatedTerm(): void
    {
        $this->resetPage();
    }

    public function updatedOnlyZeroResults(): void
    {
        $this->resetPage();
    }

    public function updatedIncludeAdminSearches(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->from = now()->subDays(30)->toDateString();
        $this->to = now()->toDateString();
        $this->userId = '';
        $this->source = '';
        $this->term = '';
        $this->onlyZeroResults = false;
        $this->includeAdminSearches = false;
        $this->resetPage();
    }

    public function resetLogs(KompendiumSearchAnalyticsService $analytics): void
    {
        $deleted = $analytics->resetAll();
        $this->resetPage();

        unset(
            $this->summary,
            $this->recentSearches,
            $this->topQueries,
            $this->zeroResultQueries,
            $this->userStats,
            $this->sourceDistribution,
            $this->searchesOverTime,
            $this->usersForFilter,
            $this->sourceOptions,
        );

        session()->flash('success', "{$deleted} Suchlog-Einträge wurden gelöscht.");
    }

    #[Computed]
    public function summary(): array
    {
        return app(KompendiumSearchAnalyticsService::class)->summary($this->filters());
    }

    #[Computed]
    public function recentSearches(): LengthAwarePaginator
    {
        return app(KompendiumSearchAnalyticsService::class)->recentSearches($this->filters());
    }

    #[Computed]
    public function topQueries(): Collection
    {
        return app(KompendiumSearchAnalyticsService::class)->topQueries($this->filters());
    }

    #[Computed]
    public function zeroResultQueries(): Collection
    {
        return app(KompendiumSearchAnalyticsService::class)->zeroResultQueries($this->filters());
    }

    #[Computed]
    public function userStats(): Collection
    {
        return app(KompendiumSearchAnalyticsService::class)->userStats($this->filters());
    }

    #[Computed]
    public function sourceDistribution(): Collection
    {
        return app(KompendiumSearchAnalyticsService::class)->sourceDistribution($this->filters());
    }

    #[Computed]
    public function searchesOverTime(): Collection
    {
        return app(KompendiumSearchAnalyticsService::class)->searchesOverTime($this->filters());
    }

    #[Computed]
    public function usersForFilter(): Collection
    {
        return app(KompendiumSearchAnalyticsService::class)->usersForFilter();
    }

    #[Computed]
    public function sourceOptions(): array
    {
        return collect([
            ['id' => '', 'name' => 'Alle Quellen'],
            ['id' => 'search_submit', 'name' => 'Suchstart'],
            ['id' => 'filter_change', 'name' => 'Filter geändert'],
            ['id' => 'sort_change', 'name' => 'Sortierung geändert'],
            ['id' => 'api_search', 'name' => 'API-Suche'],
        ])->merge(
            app(KompendiumSearchAnalyticsService::class)->availableSources()
        )
            ->unique('id')
            ->values()
            ->toArray();
    }

    public function sourceLabel(string $source): string
    {
        return app(KompendiumSearchAnalyticsService::class)->sourceLabel($source);
    }

    private function filters(): array
    {
        return [
            'from' => filled($this->from) ? $this->from.' 00:00:00' : null,
            'to' => filled($this->to) ? $this->to.' 23:59:59' : null,
            'user_id' => $this->userId,
            'source' => $this->source,
            'term' => $this->term,
            'only_zero_results' => $this->onlyZeroResults,
            'include_admin_searches' => $this->includeAdminSearches,
        ];
    }

    public function render()
    {
        return view('livewire.kompendium-search-analytics-dashboard');
    }
}
