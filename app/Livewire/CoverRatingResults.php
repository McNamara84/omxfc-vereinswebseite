<?php

namespace App\Livewire;

use App\Enums\BookType;
use App\Services\CoverRatings\CoverRatingAccessService;
use App\Services\CoverRatings\CoverRatingResultService;
use App\Services\CoverRatings\CoverSelectionService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class CoverRatingResults extends Component
{
    use WithPagination;

    #[Url(except: 'all')]
    public string $series = 'all';

    #[Url(except: 'best')]
    public string $sort = 'best';

    public function mount(CoverRatingAccessService $access): void
    {
        $access->ensureMemberAccess();
        app(CoverSelectionService::class)->resolveSeries($this->series);
        $this->validateSort($this->sort);
    }

    public function updatedSeries(string $value): void
    {
        app(CoverRatingAccessService::class)->ensureMemberAccess();
        app(CoverSelectionService::class)->resolveSeries($value);
        $this->resetPage(pageName: 'resultsPage');
        unset($this->results);
    }

    public function updatedSort(string $value): void
    {
        app(CoverRatingAccessService::class)->ensureMemberAccess();
        $this->validateSort($value);
        $this->resetPage(pageName: 'resultsPage');
        unset($this->results);
    }

    #[Computed]
    public function results()
    {
        $user = app(CoverRatingAccessService::class)->ensureMemberAccess();

        return app(CoverRatingResultService::class)
            ->resultsQuery($user, $this->series, $this->sort)
            ->paginate(18, pageName: 'resultsPage');
    }

    #[Computed]
    public function seriesOptions(): array
    {
        return BookType::options(includeAll: true);
    }

    #[Computed]
    public function sortOptions(): array
    {
        return [
            ['id' => 'best', 'name' => 'Beste Bewertung'],
            ['id' => 'votes', 'name' => 'Meiste Stimmen'],
            ['id' => 'recent', 'name' => 'Zuletzt bewertet'],
            ['id' => 'number', 'name' => 'Heftnummer'],
        ];
    }

    public function render()
    {
        return view('livewire.cover-rating-results', [
            'minimumVotes' => (int) config('cover-ratings.results_min_votes', 3),
        ])->layout('layouts.app', ['title' => 'Cover-Ergebnisse']);
    }

    private function validateSort(string $sort): void
    {
        abort_unless(in_array($sort, ['best', 'votes', 'recent', 'number'], true), 404);
    }
}
